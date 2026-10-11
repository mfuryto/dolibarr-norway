"""Storage guards must refuse ambiguity before stopping or copying data."""
import importlib.util
import json
from pathlib import Path
import tempfile
import unittest
from unittest.mock import patch

spec = importlib.util.spec_from_file_location('migration', Path(__file__).resolve().parents[1] / 'scripts/migrate-storage.py')
migration = importlib.util.module_from_spec(spec)
spec.loader.exec_module(migration)


class StorageMigrationGuards(unittest.TestCase):
    def check_layout(self, root, layout, database='accounting'):
        containers = {}
        for service, paths in {'db': [('/var/lib/mysql', 'database')],
                               'web': [('/var/www/documents', 'documents'), ('/var/www/html/conf', 'config')]}.items():
            containers[service] = {'Config': {'Env': ['DOLI_DB_NAME=' + database],
                'Labels': {'com.docker.compose.project': 'dolibarr-norway-accounting'}},
                'Mounts': [{'Destination': target, 'Type': layout[name],
                    'Source': str(root/'data'/name), 'Name': name} for target, name in paths]}
        calls = []
        def fake(*args, capture=False):
            calls.append(args)
            if args[:2] == ('docker', 'inspect'):
                return json.dumps([containers[args[2]]])
            if 'ps' in args:
                return args[-1]
            raise AssertionError('Unexpected Docker mutation: ' + str(args))
        with patch.object(migration, 'ROOT', root), patch.object(migration, 'run', fake), \
                patch.dict('os.environ', {'NORWAY_COMPOSE_PROJECT': 'dolibarr-norway-accounting'}):
            migration.migrate()
        return calls

    def test_existing_bind_layout_needs_no_copy(self):
        with tempfile.TemporaryDirectory() as directory:
            self.check_layout(Path(directory), dict.fromkeys(('database', 'documents', 'config'), 'bind'))

    def test_mixed_layout_refused_before_stopping(self):
        with tempfile.TemporaryDirectory() as directory, self.assertRaisesRegex(RuntimeError, 'Mixed storage'):
            self.check_layout(Path(directory), {'database': 'volume', 'documents': 'bind', 'config': 'bind'})

    def test_development_database_refused(self):
        with tempfile.TemporaryDirectory() as directory, self.assertRaisesRegex(RuntimeError, 'Unexpected database'):
            self.check_layout(Path(directory), dict.fromkeys(('database', 'documents', 'config'), 'volume'), 'norway_test')
