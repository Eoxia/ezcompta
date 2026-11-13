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

CREATE TABLE IF NOT EXISTS llx_bank_record
(
  rowid             integer     AUTO_INCREMENT PRIMARY KEY,
  ref 				varchar(50) NOT NULL,
  entity			integer		DEFAULT 1 NOT NULL,
  ref_ext			varchar(255) NULL,
  fk_bank			integer		NOT NULL,
  dt_from			date		NOT NULL,
  dt_to				date		NOT NULL,
  opening_balance	double(24,8) NULL DEFAULT NULL,
  closing_balance	double(24,8) NULL DEFAULT NULL,
  total_debit		double(24,8) NULL DEFAULT NULL,
  total_credit		double(24,8) NULL DEFAULT NULL,
  nb_operations		integer		NULL DEFAULT NULL,
  status			smallint(6)	DEFAULT 0 NOT NULL,
  fk_user_author	integer		NULL DEFAULT NULL,
  fk_user_modif		integer		NULL DEFAULT NULL,
  fk_user_valid		integer		NULL DEFAULT NULL,
  note_private		text		NULL DEFAULT NULL,
  note_public		text		NULL DEFAULT NULL,
  model_pdf			varchar(255) NULL DEFAULT NULL,
  last_main_doc		varchar(255) NULL DEFAULT NULL,
  date_creation datetime NOT NULL,
  date_valid datetime NULL,
  tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  import_key		varchar(14) NULL DEFAULT NULL
)ENGINE=innodb;

ALTER TABLE llx_bank_record ADD COLUMN entity integer DEFAULT 1 NOT NULL AFTER ref;
ALTER TABLE llx_bank_record ADD COLUMN ref_ext varchar(255) NULL AFTER entity;
ALTER TABLE llx_bank_record ADD COLUMN opening_balance double(24,8) NULL DEFAULT NULL AFTER dt_to;
ALTER TABLE llx_bank_record ADD COLUMN closing_balance double(24,8) NULL DEFAULT NULL AFTER opening_balance;
ALTER TABLE llx_bank_record ADD COLUMN total_debit double(24,8) NULL DEFAULT NULL AFTER closing_balance;
ALTER TABLE llx_bank_record ADD COLUMN total_credit double(24,8) NULL DEFAULT NULL AFTER total_debit;
ALTER TABLE llx_bank_record ADD COLUMN nb_operations integer NULL DEFAULT NULL AFTER total_credit;
ALTER TABLE llx_bank_record ADD COLUMN status smallint(6) NOT NULL DEFAULT 0 AFTER nb_operations;
ALTER TABLE llx_bank_record ADD COLUMN fk_user_author integer NULL DEFAULT NULL AFTER status;
ALTER TABLE llx_bank_record ADD COLUMN fk_user_modif integer NULL DEFAULT NULL AFTER fk_user_author;
ALTER TABLE llx_bank_record ADD COLUMN fk_user_valid integer NULL DEFAULT NULL AFTER fk_user_modif;
ALTER TABLE llx_bank_record ADD COLUMN note_private text NULL DEFAULT NULL AFTER fk_user_valid;
ALTER TABLE llx_bank_record ADD COLUMN note_public text NULL DEFAULT NULL AFTER note_private;
ALTER TABLE llx_bank_record ADD COLUMN model_pdf varchar(255) NULL DEFAULT NULL AFTER note_public;
ALTER TABLE llx_bank_record ADD COLUMN last_main_doc varchar(255) NULL DEFAULT NULL AFTER model_pdf;
ALTER TABLE llx_bank_record ADD COLUMN import_key varchar(14) NULL DEFAULT NULL;
