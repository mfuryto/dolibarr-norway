import importlib.util
from pathlib import Path
import tempfile
import subprocess
import sys
import unittest

spec = importlib.util.spec_from_file_location('install_env', Path(__file__).resolve().parents[1] / 'scripts/init-install-env.py')
module = importlib.util.module_from_spec(spec)
spec.loader.exec_module(module)


class InstallConfigurationTests(unittest.TestCase):
    def test_private_unique_credentials_and_no_overwrite(self):
        with tempfile.TemporaryDirectory() as directory:
            first, second = (Path(directory) / name for name in ('first.env', 'second.env'))
            for p in (first, second):
                module.create_config('SYNTHETIC ENK', 'https://example.invalid', 'adminuser', p)
                self.assertEqual(p.stat().st_mode & 0o777, 0o600)
            self.assertNotEqual(first.read_text(), second.read_text())
            with self.assertRaises(FileExistsError):
                module.create_config('SYNTHETIC ENK', 'https://example.invalid', 'adminuser', first)

    def test_rejects_unsafe_or_incorrect_settings_before_creating_file(self):
        with tempfile.TemporaryDirectory() as directory:
            p = Path(directory) / 'unsafe.env'
            for company, url, admin in [('', 'https://example.invalid', 'adminuser'),
                ("Bad'name", 'https://example.invalid', 'adminuser'),
                ('Company', 'http://example.invalid', 'adminuser'),
                ('Company', 'https://example.invalid/path', 'adminuser'),
                ('Company', "https://bad'host", 'adminuser'),
                ('Company', 'https://example.invalid', 'a\nb')]:
                with self.subTest(url=url), self.assertRaises(ValueError):
                    module.create_config(company, url, admin, p)
                self.assertFalse(p.exists())

    def test_public_url_is_required_on_command_line(self):
        script = Path(__file__).resolve().parents[1] / 'scripts/init-install-env.py'
        result = subprocess.run([sys.executable, str(script), '--company-name', 'SYNTHETIC ONLY'],
                                text=True, capture_output=True)
        self.assertEqual(result.returncode, 2)
        self.assertIn('--url', result.stderr)
