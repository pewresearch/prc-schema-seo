# Schema JSON-LD Generator

This is the generator class for the PRC Schema SEO plugin. It is responsible for generating the JSON-LD schema for the post and term objects, archives, and various core templates like 404, search, and home pages.

To add a new schema type, you need to add a new method to the generator class. The method should be named `generate_<schema_type>_schema()`. The method should use the Spatie schema-org library to generate the schema object.
