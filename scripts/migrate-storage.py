#!/usr/bin/env python3
"""Move a stopped installation from named volumes to project-local storage."""
import json
import os
from pathlib import Path
import subprocess

ROOT = Path(__file__).resolve().parents[1]


def run(*args, capture=False):
    result = subprocess.run(args, cwd=ROOT, check=True, text=True,
                            stdout=subprocess.PIPE if capture else None)
    return result.stdout.strip() if capture else None


def migrate():
    project = os.environ.get('NORWAY_COMPOSE_PROJECT', 'dolibarr-norway-accounting')
    compose = ['docker', 'compose', '--env-file', 'deploy/.env', '-f', 'deploy/compose.yaml', '-p', project]
    ids = {service: run(*compose, 'ps', '-a', '-q', service, capture=True) for service in ('db', 'web')}
    if not all(ids.values()):
        raise RuntimeError('Existing database and web containers required; no data was moved.')
    containers = {service: json.loads(run('docker', 'inspect', cid, capture=True))[0]
                  for service, cid in ids.items()}
    web_env = dict(item.split('=', 1) for item in containers['web']['Config']['Env'] if '=' in item)
    if web_env.get('DOLI_DB_NAME') != 'accounting':
        raise RuntimeError('Unexpected database; refusing to migrate development data.')
    for container in containers.values():
        if container['Config']['Labels'].get('com.docker.compose.project') != project:
            raise RuntimeError('Unexpected Compose project.')
    specs = [('db', '/var/lib/mysql', 'database'),
             ('web', '/var/www/documents', 'documents'),
             ('web', '/var/www/html/conf', 'config')]
    moves = []
    for service, target, directory in specs:
        mounts = [m for m in containers[service]['Mounts'] if m['Destination'] == target]
        if len(mounts) != 1:
            raise RuntimeError('Expected persistent mount is missing: ' + target)
        mount = mounts[0]
        destination = ROOT / 'data' / directory
        if mount['Type'] == 'bind' and Path(mount['Source']).resolve() == destination.resolve():
            moves.append((service, mount, destination, True))
        elif mount['Type'] == 'volume':
            moves.append((service, mount, destination, False))
        else:
            raise RuntimeError('Unexpected storage location; automatic migration refused.')
    if all(m[3] for m in moves):
        print('Persistent data already resides in the project data directory.')
        return
    if any(m[3] for m in moves):
        raise RuntimeError('Mixed storage layout; review required before migration.')
    data = ROOT / 'data'
    data.mkdir(mode=0o700, exist_ok=True)
    # Verify destination emptiness through Docker, which can inspect preserved ownership.
    for service, mount, destination, _ in moves:
        destination.mkdir(exist_ok=True)
        run('docker', 'run', '--rm', '--network', 'none', '--mount',
            'type=bind,src=' + str(destination) + ',dst=/destination', '--entrypoint', 'sh',
            containers['db']['Config']['Image'], '-c', 'test -z "$(ls -A /destination)"')
    running = [ids[s] for s in ('db', 'web') if containers[s]['State']['Running']]
    success = False
    try:
        run('docker', 'stop', ids['web'], ids['db'])
        for service, mount, destination, _ in moves:
            run('docker', 'run', '--rm', '--network', 'none', '--mount',
                'type=volume,src=' + mount['Name'] + ',dst=/source,readonly', '--mount',
                'type=bind,src=' + str(destination) + ',dst=/destination', '--entrypoint', 'sh',
                containers['db']['Config']['Image'], '-c',
                'cp -a /source/. /destination/ && diff -qr /source /destination')
        success = True
        print('Persistent data copied and verified. Original named volumes were retained.')
    finally:
        if not success and running:
            run('docker', 'start', *running)
            print('Migration failed; original containers restarted. Inspect partial copies before retrying.')


if __name__ == '__main__':
    migrate()
