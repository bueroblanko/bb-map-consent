"""Build a WordPress ZIP containing a stable plugin directory, no developer files."""
from pathlib import Path
import re
import zipfile
import shutil
root = Path(__file__).resolve().parents[1]
version = re.search(r'\* Version: (\d+\.\d+\.\d+)', (root / 'bb-map-consent.php').read_text())[1]
out = root / 'dist'
out.mkdir(exist_ok=True)
with zipfile.ZipFile(out / f'bb-consent-{version}.zip', 'w', zipfile.ZIP_DEFLATED) as archive:
    for name in ['bb-map-consent.php', 'README.md', 'LICENSE', 'assets', 'includes']:
        path = root / name
        for source in ([path] if path.is_file() else sorted(path.rglob('*'))):
            if source.is_file():
                archive.write(source, 'bb-consent/' + str(source.relative_to(root)))
# Compatibility asset for updaters before 0.5.5, with the same canonical root.
shutil.copyfile(out / f'bb-consent-{version}.zip', out / f'bb-map-consent-{version}.zip')
print(version)
