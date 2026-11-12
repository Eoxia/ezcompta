-- ===================================================================
-- Copyright (C) 2026 Laurent MAGNIN <laurent.magnin@evarisk.com>
--
-- This program is free software; you can redistribute it and/or modify
-- it under the terms of the GNU General Public License as published by
-- the Free Software Foundation; either version 3 of the License, or
-- (at your option) any later version.
--
-- This program is distributed in the hope that it will be useful,
-- but WITHOUT ANY WARRANTY; without even the implied warranty of
-- MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
-- GNU General Public License for more details.
--
-- You should have received a copy of the GNU General Public License
-- along with this program. If not, see <https://www.gnu.org/licenses/>.
--
-- ===================================================================

-- Table patch for llx_bank_record (following ISO 20022 CAMT.053 & MT940 banking standards)
-- Only official bank statement values are stored natively in the header.
-- Calculated values from transactions are computed on the fly and are NOT persisted.

ALTER TABLE llx_bank_record ADD COLUMN IF NOT EXISTS opening_balance double(24,8) NULL DEFAULT NULL AFTER dt_to;
ALTER TABLE llx_bank_record ADD COLUMN IF NOT EXISTS closing_balance double(24,8) NULL DEFAULT NULL AFTER opening_balance;
ALTER TABLE llx_bank_record ADD COLUMN IF NOT EXISTS total_debit double(24,8) NULL DEFAULT NULL AFTER closing_balance;
ALTER TABLE llx_bank_record ADD COLUMN IF NOT EXISTS total_credit double(24,8) NULL DEFAULT NULL AFTER total_debit;
ALTER TABLE llx_bank_record ADD COLUMN IF NOT EXISTS nb_operations integer NULL DEFAULT NULL AFTER total_credit;
ALTER TABLE llx_bank_record ADD COLUMN IF NOT EXISTS status smallint(6) NOT NULL DEFAULT 0 AFTER nb_operations;
ALTER TABLE llx_bank_record ADD COLUMN IF NOT EXISTS fk_user_author integer NULL DEFAULT NULL AFTER status;
ALTER TABLE llx_bank_record ADD COLUMN IF NOT EXISTS fk_user_valid integer NULL DEFAULT NULL AFTER fk_user_author;
ALTER TABLE llx_bank_record ADD COLUMN IF NOT EXISTS note text NULL DEFAULT NULL AFTER fk_user_valid;
