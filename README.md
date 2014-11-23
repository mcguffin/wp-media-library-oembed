WordPress Media Library Oembed
==============================

Embed externally hosted Content like from Vimeo or YouTube in your media library.

Features
--------
- Adds oembedded content to your Media Library.
- Adds administration option to allow / disallow certain oembed providers.


Restrictions
------------
- Does not support image captions yet.
- No Thumbnail download.

Plugin API
----------

#### Filter `mla_oembed_attachment_data`

Filter for attachment post data before it is passed to `wp_insert_post()`.

Example:
```
function my_mla_oembed_attachment_data( $attachment_data , $oembed_response  ) {
	
	return $attachment_data;
}
add_filter('mla_oembed_attachment_data','my_mla_oembed_attachment_data' , 10 , 2 );
```

