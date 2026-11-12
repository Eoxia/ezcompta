#!/usr/bin/env python3
# Copyright (C) 2026 Laurent MAGNIN <laurent.magnin@evarisk.com>
#
# This program is free software; you can redistribute it and/or modify
# it under the terms of the GNU General Public License as published by
# the Free Software Foundation; either version 3 of the License, or
# (at your option) any later version.
#
# This program is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU General Public License for more details.
#
# You should have received a copy of the GNU General Public License
# along with this program. If not, see <https://www.gnu.org/licenses/>.

import sys, os, re, json
try:
    import fitz
except ImportError:
    print(json.dumps({"error": "PyMuPDF (fitz) is not installed"}))
    sys.exit(1)

def parse_pdf(pdf_path):
    if not os.path.exists(pdf_path):
        return {"error": f"File not found: {pdf_path}"}
    
    doc = fitz.open(pdf_path)
    text = '\n'.join([p.get_text() for p in doc])
    
    # 1. Ref / Statement number (e.g. Réf : 010)
    m_ref = re.search(r'Réf\s*:\s*(\w+)', text)
    ref = m_ref.group(1) if m_ref else None
    
    # 2. Date from filename (e.g. au 2026-10-30.pdf)
    m_file_date = re.search(r'au\s+(\d{4}-\d{2}-\d{2})', os.path.basename(pdf_path))
    dt_end_file = m_file_date.group(1) if m_file_date else None
    
    # 3. All balances (SOLDE CREDITEUR / DEBITEUR / NUL)
    pattern_solde = r'SOLDE\s+(CREDITEUR|DEBITEUR|NUL)\s+AU\s+(\d{2}/\d{2}/\d{4})(?:\s+([\d\.\s]+,\d{2}))?'
    soldes = list(re.finditer(pattern_solde, text))
    
    bal_start = None
    dt_start = None
    bal_end = None
    dt_end = None
    
    if len(soldes) >= 1:
        first = soldes[0]
        dt_start = first.group(2) # DD/MM/YYYY
        if first.group(1) == 'NUL':
            bal_start = 0.0
        elif first.group(3):
            sign = 1 if first.group(1) == 'CREDITEUR' else -1
            bal_start = float(first.group(3).replace(' ', '').replace('.', '').replace(',', '.')) * sign
            
    if len(soldes) >= 2:
        last = soldes[-1]
        dt_end = last.group(2)
        if last.group(1) == 'NUL':
            bal_end = 0.0
        elif last.group(3):
            sign = 1 if last.group(1) == 'CREDITEUR' else -1
            bal_end = float(last.group(3).replace(' ', '').replace('.', '').replace(',', '.')) * sign
            
    # 4. Total movements (Débit / Crédit)
    # Total des mouvements \n Débit EUROS \n Crédit EUROS \n 4.475,63 \n 2.275,20
    m_tot = re.search(r'Total des mouvements\s+([\d\.\s]+,\d{2})\s+([\d\.\s]+,\d{2})', text)
    tot_debit = None
    tot_credit = None
    if m_tot:
        tot_debit = float(m_tot.group(1).replace(' ', '').replace('.', '').replace(',', '.'))
        tot_credit = float(m_tot.group(2).replace(' ', '').replace('.', '').replace(',', '.'))

    # 5. Number of operations (double dates: date op / date valeur)
    ops_matches = re.findall(r'(\d{2}/\d{2}/\d{4})\s*\n\s*(\d{2}/\d{2}/\d{4})', text)
    nb_operations = len(ops_matches) if ops_matches else 0
        
    return {
        "ref": ref,
        "dt_start": dt_start,
        "dt_end": dt_end or dt_end_file,
        "opening_balance": bal_start,
        "closing_balance": bal_end,
        "total_debit": tot_debit,
        "total_credit": tot_credit,
        "nb_operations": nb_operations
    }

if __name__ == '__main__':
    if len(sys.argv) < 2:
        print(json.dumps({"error": "No PDF path provided"}))
        sys.exit(1)
    res = parse_pdf(sys.argv[1])
    print(json.dumps(res, ensure_ascii=False))
