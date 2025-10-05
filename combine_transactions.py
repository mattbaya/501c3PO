#!/usr/bin/env python3
"""
Combine bank transactions and Stripe transactions into a single chronological table
"""

import csv
from datetime import datetime
from collections import defaultdict

def parse_bank_transactions(filepath):
    """Parse MountainOne Bank CSV file"""
    transactions = []

    with open(filepath, 'r') as f:
        reader = csv.DictReader(f)
        for row in reader:
            # Parse date
            date_str = row['Post Date']
            if not date_str:
                continue
            date = datetime.strptime(date_str, '%m/%d/%Y')

            # Determine amount (negative for debits, positive for credits)
            debit = float(row['Debit']) if row['Debit'] else 0
            credit = float(row['Credit']) if row['Credit'] else 0
            amount = credit - debit

            transactions.append({
                'date': date,
                'source': 'Bank',
                'description': row['Description'],
                'amount': amount,
                'debit': debit,
                'credit': credit,
                'check_num': row['Check'],
                'status': row['Status'],
                'type': 'Bank Transaction'
            })

    return transactions

def parse_stripe_transactions(filepath):
    """Parse Stripe unified payments CSV file"""
    transactions = []

    with open(filepath, 'r') as f:
        reader = csv.DictReader(f)
        for row in reader:
            # Parse date
            date_str = row['Created date (UTC)']
            if not date_str:
                continue
            date = datetime.strptime(date_str, '%m/%d/%Y %H:%M')

            # Parse amount and fee
            amount = float(row['Amount']) if row['Amount'] else 0
            fee = float(row['Fee']) if row['Fee'] else 0
            refunded = float(row['Amount Refunded']) if row['Amount Refunded'] else 0

            # Net amount after fees
            net_amount = amount - fee

            # If refunded, mark as negative
            if refunded > 0:
                net_amount = -net_amount

            # Get description
            description = row.get('Description', '')
            if not description:
                # Try to construct from other fields
                if row.get('payer_name (metadata)'):
                    description = f"Payment from {row['payer_name (metadata)']}"
                elif row.get('Customer Email'):
                    description = f"Payment from {row['Customer Email']}"

            transactions.append({
                'date': date,
                'source': 'Stripe',
                'description': description,
                'amount': amount,
                'fee': fee,
                'net_amount': net_amount,
                'refunded': refunded,
                'status': row['Status'],
                'customer_email': row.get('Customer Email', ''),
                'type': 'Stripe Payment'
            })

    return transactions

def generate_combined_table(bank_file, stripe_file, output_file):
    """Generate combined transaction table"""

    # Parse both sources
    print("Parsing bank transactions...")
    bank_trans = parse_bank_transactions(bank_file)
    print(f"Found {len(bank_trans)} bank transactions")

    print("Parsing Stripe transactions...")
    stripe_trans = parse_stripe_transactions(stripe_file)
    print(f"Found {len(stripe_trans)} Stripe transactions")

    # Combine and sort by date
    all_trans = bank_trans + stripe_trans
    all_trans.sort(key=lambda x: x['date'])

    # Write to CSV
    print(f"\nWriting combined table to {output_file}...")
    with open(output_file, 'w', newline='') as f:
        fieldnames = ['date', 'source', 'type', 'description', 'amount', 'fee', 'net_amount',
                     'debit', 'credit', 'refunded', 'status', 'check_num', 'customer_email']
        writer = csv.DictWriter(f, fieldnames=fieldnames, extrasaction='ignore')

        writer.writeheader()
        for trans in all_trans:
            # Format date for output
            trans['date'] = trans['date'].strftime('%Y-%m-%d %H:%M')
            writer.writerow(trans)

    print(f"✓ Combined {len(all_trans)} total transactions")

    # Print summary
    print("\n" + "="*80)
    print("TRANSACTION SUMMARY")
    print("="*80)
    print(f"Total transactions: {len(all_trans)}")
    print(f"  - Bank transactions: {len(bank_trans)}")
    print(f"  - Stripe transactions: {len(stripe_trans)}")

    # Calculate totals
    bank_credits = sum(t['credit'] for t in bank_trans)
    bank_debits = sum(t['debit'] for t in bank_trans)
    stripe_gross = sum(t['amount'] for t in stripe_trans if t['refunded'] == 0)
    stripe_fees = sum(t['fee'] for t in stripe_trans)
    stripe_net = sum(t['net_amount'] for t in stripe_trans if t['refunded'] == 0)
    stripe_refunded = sum(t['refunded'] for t in stripe_trans)

    print(f"\nBank Summary:")
    print(f"  Total Credits (Income): ${bank_credits:,.2f}")
    print(f"  Total Debits (Expenses): ${bank_debits:,.2f}")
    print(f"  Net: ${bank_credits - bank_debits:,.2f}")

    print(f"\nStripe Summary:")
    print(f"  Gross Payments: ${stripe_gross:,.2f}")
    print(f"  Stripe Fees: ${stripe_fees:,.2f}")
    print(f"  Net Payments: ${stripe_net:,.2f}")
    print(f"  Total Refunded: ${stripe_refunded:,.2f}")

    print("\n" + "="*80)
    print(f"Output saved to: {output_file}")
    print("="*80)

if __name__ == '__main__':
    bank_file = '/home/swca/scripts/501c3PO/treasurer-docs/MoutainOne Bank AccountHistory_Jan - Sept 2025.csv'
    stripe_file = '/home/swca/scripts/501c3PO/treasurer-docs/STRIPE unified_payments_Jan - Sept 2025.csv'
    output_file = '/home/swca/scripts/501c3PO/treasurer-docs/COMBINED_Transactions_Jan-Sept_2025.csv'

    generate_combined_table(bank_file, stripe_file, output_file)
