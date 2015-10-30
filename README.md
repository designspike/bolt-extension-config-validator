Summary: Validate your Bolt CMS config files according to schemas.

This extension adds a menu item at Extras > Validate config files.

When you clidk the link, you'll see a page with validation results spitting out stuff like this:

  * contenttypes.yml is invalid: The node 'root.entries' has not allowed extra key(s): singular_slog
  * taxonomy.yml valid.
  * config.yml valid.
  * config_local.yml valid.
  * menu.yml valid.
  * permissions.yml valid.
  * routing.yml valid.

It validates the core config files according to formally specified schemas.  The schemas themselves are included in the extension and are yaml files in the format defined by https://github.com/romaricdrigon/MetaYaml
