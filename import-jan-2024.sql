-- Import January 2024 Bank Transactions
-- Account: 0880269618
-- Starting Balance: 6849.28
-- Ending Balance: 6241.30

USE swca_swca2019;

-- Insert January 2024 transactions (sorted by date)
INSERT INTO wp_swca_bank_transactions (account_number, post_date, transaction_date, check_number, description, debit, credit, status, balance)
VALUES
('0880269618', '2024-01-02', '2024-01-02', '619', 'Check', 93.70, 0.00, 'Posted', 6755.58),
('0880269618', '2024-01-02', '2024-01-02', '622', 'Check', 11.00, 0.00, 'Posted', 6744.58),
('0880269618', '2024-01-04', '2024-01-04', '615', 'Check', 43.94, 0.00, 'Posted', 6700.64),
('0880269618', '2024-01-04', '2024-01-04', '623', 'Check', 637.20, 0.00, 'Posted', 6063.44),
('0880269618', '2024-01-04', '2024-01-04', '', 'ACH Credit TRANSFER STRIPE ID4270465600', 0.00, 130.48, 'Posted', 6193.92),
('0880269618', '2024-01-05', '2024-01-05', '', 'ACH Credit TRANSFER STRIPE ID4270465600', 0.00, 33.68, 'Posted', 6227.60),
('0880269618', '2024-01-09', '2024-01-09', '', 'ACH Credit TRANSFER STRIPE ID4270465600', 0.00, 33.68, 'Posted', 6261.28),
('0880269618', '2024-01-12', '2024-01-12', '', 'Deposit', 0.00, 35.00, 'Posted', 6296.28),
('0880269618', '2024-01-16', '2024-01-16', '', 'ACH Credit TRANSFER STRIPE ID4270465600', 0.00, 33.68, 'Posted', 6329.96),
('0880269618', '2024-01-19', '2024-01-19', '624', 'Check', 125.00, 0.00, 'Posted', 6204.96),
('0880269618', '2024-01-31', '2024-01-31', '', 'ACH Credit TRANSFER STRIPE ID4270465600', 0.00, 33.68, 'Posted', 6238.64),
('0880269618', '2024-01-31', '2024-01-31', '', 'Interest Credit', 0.00, 2.66, 'Posted', 6241.30);

SELECT COUNT(*) as 'January 2024 Transactions Imported' FROM wp_swca_bank_transactions WHERE post_date BETWEEN '2024-01-01' AND '2024-01-31';
