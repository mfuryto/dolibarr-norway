"""Updates must fail before touching Docker when prerequisites are unsafe."""
import fcntl
import os
from pathlib import Path
import shutil
import subprocess
import tempfile
import unittest

ROOT = Path(__file__).resolve().parents[1]


class UpdateGuards(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory()
        self.addCleanup(self.temp.cleanup)
        self.root = Path(self.temp.name)
        (self.root / 'scripts').mkdir()
        shutil.copy2(ROOT / 'scripts/update-installation.sh', self.root / 'scripts/')
        (self.root / 'deploy').mkdir()
        (self.root / '.gitignore').write_text('deploy/.env\n.installation-update.lock\n')
        self.git('init', '-q', '-b', 'main')
        self.git('add', '.')
        self.git('-c', 'user.name=Dolibarr Norway verification', '-c',
                 'user.email=verification@dolibarr-norway.invalid', 'commit', '-qm', 'Fixture')
        self.bin = self.root / 'fake-bin'
        self.bin.mkdir()
        (self.bin / 'docker').write_text('#!/bin/sh\necho DOCKER_CALLED\nexit 90\n')
        (self.bin / 'docker').chmod(0o700)
        with (self.root / '.git/info/exclude').open('a') as stream:
            stream.write('fake-bin/\n')

    def git(self, *args):
        return subprocess.run(['git', *args], cwd=self.root, check=True,
                              stdout=subprocess.PIPE, stderr=subprocess.PIPE)

    def refuse(self, message):
        env = dict(os.environ, PATH=str(self.bin) + ':' + os.environ['PATH'])
        result = subprocess.run(['bash', 'scripts/update-installation.sh'], cwd=self.root,
                                env=env, text=True, capture_output=True)
        self.assertNotEqual(result.returncode, 0)
        self.assertIn(message, result.stderr)
        self.assertNotIn('DOCKER_CALLED', result.stdout + result.stderr)

    def test_missing_credentials(self):
        self.refuse('Missing deploy/.env')

    def test_staging_cannot_update_server(self):
        self.git('switch', '-qc', 'staging')
        self.refuse('stable main branch')

    def test_dirty_source_cannot_update_server(self):
        (self.root / 'deploy/.env').write_text('SYNTHETIC=1\n')
        script = self.root / 'scripts/update-installation.sh'
        script.write_text(script.read_text() + '\n# changed\n')
        self.refuse('Working tree must be clean')

    def test_concurrent_update_refused(self):
        with (self.root / '.installation-update.lock').open('w') as lock:
            fcntl.flock(lock, fcntl.LOCK_EX | fcntl.LOCK_NB)
            self.refuse('Another installation update')
