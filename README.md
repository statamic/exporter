# Statamic Exporter ![Statamic 1.9](https://img.shields.io/badge/statamic-1.9-yellow.svg?style=flat-square)
A companion to Statamic 2's _Importer_. Could be useful for other reasons too.

## Usage
- Install the addon by copying the files into `_add-ons/exporter`.
- Visit `http://your-v1-site.com/TRIGGER/exporter/export` and copy/paste the output into V2's importer.

### Collections / Entries
Collections will be created for every folder with a `fields.yaml` in it. So, make sure that all your entry folders
have them, and that they contain a `type:` key.

The type should be `number`, `date` or `alphabetical`, depending on the type of entry ordering.
