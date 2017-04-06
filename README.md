# Statamic Exporter ![Statamic 1.9](https://img.shields.io/badge/statamic-1.9-lightgrey.svg?style=flat-square)
A companion to Statamic 2's _Importer_. Could be useful for other reasons too.

## Usage
- Install the addon by copying the files into `_add-ons/exporter`.
- Visit `http://v1-site.com/TRIGGER/exporter/export` which will download a JSON file.
- Upload the JSON file to your v2 site's importer.

### Collections / Entries
Collections will be created for every folder with a `fields.yaml` in it. So, make sure that all your entry folders
have them, and that they contain a `type:` key.

The type should be `number`, `date` or `alphabetical`, depending on the type of entry ordering.

### Globals
The exporter will take globals from a number of different places and turn them into globals.

- Variables in `_config/global.yaml` will be added to the top level `global` set.
- Variables that exist in `_config/settings.yaml` but don't exist in `_app/config/default.settings.yaml` will be
  considered ones that you added manually, and will also be added to the `global` set.
- Variables in `_themes/your-theme/theme.yaml` will be added to a `theme` global set.
