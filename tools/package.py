"""Build a deterministic development archive containing runtime/source artifacts only."""
from pathlib import Path
import hashlib
import json
import zipfile

root = Path(__file__).resolve().parent.parent
out = root / 'build'
out.mkdir(exist_ok=True)
files = [root / p for p in (
    'universal-content-moderation.php', 'autoload.php', 'uninstall.php',
    'readme.txt', 'README.md', 'LICENSE', 'CHANGELOG.md', 'composer.json', 'composer.lock',
)]
files += sorted((root / 'src').rglob('*.php'))
files += sorted(p for p in (root / 'docs').rglob('*') if p.is_file())
manifest = {str(p.relative_to(root)): hashlib.sha256(p.read_bytes()).hexdigest() for p in sorted(files)}
archive = out / 'universal-content-moderation-0.1.0-development.zip'
with zipfile.ZipFile(archive, 'w', compression=zipfile.ZIP_DEFLATED) as z:
    for p in sorted(files):
        info = zipfile.ZipInfo('universal-content-moderation/' + str(p.relative_to(root)), (2026, 1, 1, 0, 0, 0))
        info.compress_type = zipfile.ZIP_DEFLATED
        info.external_attr = 0o644 << 16
        z.writestr(info, p.read_bytes())
    info = zipfile.ZipInfo('universal-content-moderation/integrity.json', (2026, 1, 1, 0, 0, 0))
    info.compress_type = zipfile.ZIP_DEFLATED
    info.external_attr = 0o644 << 16
    z.writestr(info, json.dumps(manifest, sort_keys=True, indent=2) + '\n')
with zipfile.ZipFile(archive) as z:
    assert z.testzip() is None
    for path, expected in manifest.items():
        assert hashlib.sha256(z.read('universal-content-moderation/' + path)).hexdigest() == expected
checksum = hashlib.sha256(archive.read_bytes()).hexdigest()
(out / (archive.name + '.sha256')).write_text(f'{checksum}  {archive.name}\n')
print(f'{archive}\n{len(files)} files; sha256 {checksum}')
