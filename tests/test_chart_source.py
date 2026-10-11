"""Verify vendored Norwegian chart provenance and lossless transformation."""
import csv
import hashlib
import json
from pathlib import Path
import unittest

DATA = Path(__file__).resolve().parents[1] / 'htdocs/custom/norway/data'

class ChartSourceTests(unittest.TestCase):
    def test_pinned_source_and_conversion(self):
        source = json.loads((DATA / 'norwegian-chart-source.json').read_text())
        self.assertEqual(source['commit'], '2ff84495182bc2d6e87b616731f865677a12b213')
        self.assertEqual(source['license'], 'LGPL-3.0')
        for name, expected in source['sha256'].items():
            self.assertEqual(hashlib.sha256((DATA / name).read_bytes()).hexdigest(), expected)
        with (DATA / 'odoo-no-original.csv').open() as stream:
            original = list(csv.DictReader(stream))
        with (DATA / 'norwegian-chart-v1.csv').open() as stream:
            converted = list(csv.DictReader(stream, delimiter=';'))
        self.assertEqual(len(original), 745)
        self.assertEqual(len({row['code'] for row in original}), 745)
        self.assertEqual(converted, [{'account_number': row['code'], 'label': row['name@nb_NO']} for row in original])
        self.assertIn("'license': 'LGPL-3'", (DATA / 'odoo-no-manifest.txt').read_text())
