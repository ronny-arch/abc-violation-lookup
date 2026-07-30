#!/usr/bin/env python3
"""Build the public demo page from the REAL widget.

    python3 build.py && git add -A && git commit -m "..." && git push

WHY A BUILD SCRIPT AND NOT A SECOND COPY OF THE PAGE

This demo used to be index.html hand-concatenated from widget.html plus a block of injected
markup that re-implemented the "watch this building" panel in a <script>. That is two copies of
one product: every change to the real panel had to be made twice, and the demo Ronny sends people
would quietly drift into showing something the plugin does not do. It already had — the demo's
injected panel and the plugin's real one had different copy.

So the demo is now GENERATED. The only thing that differs from the shipped widget is a handful of
labelled substitutions below, each of which exists because a static GitHub Pages URL has no
back-end. Everything a visitor sees — the panel, the copy, the services, the boxes — comes from
abc-handoff/widget.html and cannot drift from it.

WHAT THE SUBSTITUTIONS DO

1. ABC_API_BASE. The real widget only renders the monitoring panel when it is running as the
   WordPress plugin (its path contains /wp-content/plugins/) or when it is given ?api=. Pages is
   neither, so the panel could never appear — which is exactly the thing the VA could not find.
   The demo pins it to a sentinel so the REAL panel renders, from the real code path.

2. A fetch shim for that sentinel. Nothing is posted anywhere; the two forms answer the way the
   live server answers, and say on screen that they are a demonstration. The alternative —
   letting the fetch 404 — would exercise the widget's honest "we cannot send this from here"
   path, which is correct behaviour but shows the VA an error instead of the product.

Nothing else is patched. If a change is needed, it belongs in widget.html.
"""

import os, re, subprocess, sys

HERE   = os.path.dirname(os.path.abspath(__file__))
WIDGET = os.path.expanduser(
    '~/Documents/GitHub/bh-violation-system/abc-handoff/widget.html')
DEMO_BASE = 'https://abc-demo.invalid/api/'   # never resolved — every call is intercepted

src = open(WIDGET, encoding='utf-8').read()

# ---- 1. pin the API base so the real monitoring panel renders -------------------------------
pat = re.compile(r"const ABC_API_BASE = \(function\(\)\{.*?\n\}\)\(\);", re.S)
if not pat.search(src):
    sys.exit('BUILD FAILED: could not find the ABC_API_BASE block — widget.html has moved on.\n'
             'Fix this script rather than hand-editing index.html; a hand-edited demo is how the\n'
             'two copies diverged in the first place.')
src = pat.sub(
    "/* DEMO BUILD — see build.py. The real derivation is replaced so the monitoring panel,\n"
    "   which only renders for the WordPress plugin, is visible on a static test link. */\n"
    f"const ABC_API_BASE = {DEMO_BASE!r};", src, count=1)

shim = open(os.path.join(HERE, '_enhance.html'), encoding='utf-8').read()
shim = shim.replace('__DEMO_BASE__', DEMO_BASE)

out = src.replace('</body>', shim + '\n</body>') if '</body>' in src else src + shim
open(os.path.join(HERE, 'index.html'), 'w', encoding='utf-8').write(out)

# ---- verify: the generated file must still parse, and must still contain the real panel -----
js = '\n'.join(re.findall(r'<script>(.*?)</script>', out, re.S))
tmp = os.path.join(HERE, '.build-check.js')
open(tmp, 'w', encoding='utf-8').write(js)
rc = subprocess.run(['node', '--check', tmp], capture_output=True, text=True)
os.remove(tmp)
if rc.returncode:
    sys.exit('BUILD FAILED: generated JS does not parse\n' + rc.stderr)

must = [
    ('the monitoring panel',      'id="watchpanel"'),
    ('the portfolio option',      "name=\"wscope\""),
    ('the proposal closer',       'Where do we send the proposal?'),
    ('the boxed violations',      'class="vcard'),
    ('the grouped service menu',  'GROUP_ORDER'),
    ('the demo fetch shim',       'ABC DEMO'),
]
missing = [name for name, needle in must if needle not in out]
if missing:
    sys.exit('BUILD FAILED: the generated page is missing ' + ', '.join(missing))

print(f'built index.html — {len(out):,} bytes')
print('  source : ' + WIDGET)
for name, _ in must:
    print(f'  ✓ {name}')
