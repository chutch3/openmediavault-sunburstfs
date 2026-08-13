# openmediavault-sunburstfs

An OpenMediaVault 7 plugin that shows a sunburst chart of disk usage by
directory for a selected file system, to help find what's eating space.

Rings are directory depth, arc size is bytes used. Hover a wedge for its
full path and size, click it to zoom in, click the center to zoom back out.

## How it works

- A background timer scans one file system per hour (`du`, depth-bounded,
  entry-count-capped) and caches the result as a static HTML+D3 page.
- The plugin's page in the UI is just a link to that cached page — nothing
  is scanned live when you open it.
- The OS root file system is never scanned.

## Requirements

- OpenMediaVault 7
- `debhelper` and `phpunit` to build the package

## Install

```sh
sudo apt-get install -y debhelper phpunit
dpkg-buildpackage -us -uc -b
sudo dpkg -i ../openmediavault-sunburstfs_*.deb
```

Or, with [`task`](https://taskfile.dev): `task install`.

The plugin appears under **Storage → Sunburst** in the OMV web UI.

## Uninstall

```sh
sudo apt-get purge openmediavault-sunburstfs
```

## Development

```sh
sudo apt-get install -y php-cli phpunit php-xml php-mbstring
phpunit
```

Tests are split into `tests/Unit` (pure logic, no I/O) and
`tests/Integration` (real `du`/filesystem calls). The package build runs
the full suite and refuses to produce a `.deb` if anything fails.

## License

GPL-3+. Vendors D3 (`templates/d3.v7.min.js`, ISC licensed) for client-side
chart rendering — see `debian/copyright`.
