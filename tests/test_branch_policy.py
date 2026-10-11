import importlib.util
from pathlib import Path
import subprocess
import unittest

ROOT = Path(__file__).resolve().parents[1]
spec = importlib.util.spec_from_file_location('pr_policy', ROOT / 'scripts/check-pr-policy.py')
policy = importlib.util.module_from_spec(spec)
spec.loader.exec_module(policy)


def event(head='staging', base='main', repo='mfuryto/dolibarr-norway'):
    return {'pull_request': {'head': {'ref': head, 'repo': {'full_name': repo}},
                             'base': {'ref': base, 'repo': {'full_name': 'mfuryto/dolibarr-norway'}}}}


class BranchPolicyTests(unittest.TestCase):
    def test_promotion_from_staging(self):
        policy.validate(event())

    def test_feature_pr_to_staging(self):
        policy.validate(event('feature', 'staging'))

    def test_rejects_feature_or_foreign_staging_to_main(self):
        for e in (event('feature'), event(repo='other/repo')):
            with self.subTest(event=e), self.assertRaises(ValueError):
                policy.validate(e)

    def test_push_event(self):
        policy.validate({})

    def test_pre_push_blocks_main_update_and_deletion(self):
        for sha in ('1' * 40, '0' * 40):
            result = subprocess.run(['sh', str(ROOT / '.githooks/pre-push')],
                input=f'refs/heads/staging {sha} refs/heads/main {"2" * 40}\n',
                text=True, capture_output=True)
            self.assertNotEqual(result.returncode, 0)

    def test_pre_push_allows_staging_update_blocks_deletion(self):
        for sha, expected in [('1' * 40, 0), ('0' * 40, 1)]:
            result = subprocess.run(['sh', str(ROOT / '.githooks/pre-push')],
                input=f'refs/heads/staging {sha} refs/heads/staging {"2" * 40}\n',
                text=True, capture_output=True)
            self.assertEqual(result.returncode, expected)
