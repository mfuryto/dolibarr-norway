import copy
import json
from pathlib import Path
import unittest
from reconcile import reconcile


class ReconciliationTests(unittest.TestCase):
    def fixture(self, name='registered'):
        return json.loads((Path(__file__).parent / 'fixtures' / (name + '.json')).read_text())

    def test_registered_balances_vat_credit_and_payments(self):
        reconcile(self.fixture())

    def test_unregistered_purchase_is_gross_expense(self):
        reconcile(self.fixture('unregistered'))

    def test_rejects_accounting_defects(self):
        changes = [
            lambda d: d['documents'][1]['lines'][0].update(debit=125001),
            lambda d: d['documents'][2].update(source='MISSING'),
            lambda d: d['documents'][3].update(amount=99999),
            lambda d: d['documents'][1].update(vat=24000, gross=124000),
            lambda d: d['expected']['closing'].update({'1920': 0}),
            lambda d: d['documents'][1]['lines'][2].update(account='9999'),
        ]
        for change in changes:
            data = copy.deepcopy(self.fixture())
            change(data)
            with self.subTest(change=change), self.assertRaises(AssertionError):
                reconcile(data)

    def test_rejects_unregistered_deduction(self):
        data = self.fixture('unregistered')
        data['documents'][2]['lines'][0]['account'] = '2710'
        with self.assertRaises(AssertionError):
            reconcile(data)


if __name__ == '__main__':
    unittest.main()
