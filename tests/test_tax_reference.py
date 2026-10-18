# SPDX-License-Identifier: GPL-3.0-or-later
import csv
import hashlib
import json
from pathlib import Path
import unittest

ROOT = Path(__file__).resolve().parents[1] / 'htdocs/custom/norway/data/tax'


class TaxReference(unittest.TestCase):
    def test_pinned_eligible_fields_and_signs(self):
        manifest = json.loads((ROOT / 'manifest.json').read_text())
        self.assertEqual(len(manifest['revision']), 40)
        for name, spec in manifest['files'].items():
            self.assertEqual(hashlib.sha256((ROOT / name).read_bytes()).hexdigest(), spec['sha256'])
        for year in (2025, 2026):
            with (ROOT / f'fields-{year}.csv').open() as f:
                rows = list(csv.DictReader(f, delimiter=';'))
            self.assertEqual(len(rows), 131)
            fields = {(r['category'], r['code']): r for r in rows}
            self.assertEqual(len(fields), len(rows))
            self.assertEqual(fields['annenDriftskostnad', '6998']['polarity'], 'negativ')
            self.assertEqual(fields['egenkapital', '2080']['polarity'], 'negativ')
            self.assertIn(('annenDriftskostnad', '6000'), fields)
            # SAF-T's extra codes must not silently become ENK tax-return fields.
            self.assertNotIn(('annenDriftskostnad', '6004'), fields)
            self.assertNotIn(('annenDriftskostnad', '6350'), fields)
            self.assertNotIn(('skattekostnad', '8300'), fields)
