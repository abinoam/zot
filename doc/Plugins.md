**Friendika Addon/Plugin development**

This is an early specification and hook details may be subject to change.

Please see the sample addon 'randplace' for a working example of using some of these features. The facebook addon provides an example of integrating both "addon" and "module" functionality. Addons work by intercepting event hooks - which must be registered. Modules work by intercepting specific page requests (by URL path). 

You must register all addons/plugins with the system in the .htconfig.php file.

     $a->config['system']['addon'] = 'plugin1name, plugin2name, another_name';

Plugin names cannot contain spaces and are used as filenames. Each addon must contain both an install and an uninstall function based on the addon/plugin name. For instance "plugin1name_install()". These two functions take no arguments and are usually responsible for registering (and unregistering) event hooks that your plugin will require. The install and uninstall functions will also be called (i.e. re-installed) if the plugin changes after installation - therefore your uninstall should not destroy data and install should consider that data may already exist. Future extensions may provide for "setup" amd "remove". 



Register your plugin hooks during installation.

    register_hook($hookname, $file, $function);

$hookname is a string and corresponds to a known Friendika hook.

$file is a pathname relative to the top-level Friendika directory. This *should* be 'addon/plugin_name/plugin_name.php' in most cases.

$function is a string and is the name of the function which will be executed when the hook is called.


Your hook callback functions will be called with at least one and possibly two arguments


    function myhook_function(&$a, &$b) {


    }


If you wish to make changes to the calling data, you must declare them as
reference variables (with '&') during function declaration.

$a is the Friendika 'App' class - which contains a wealth of information
about the current state of Friendika, such as which module has been called,
configuration info, the page contents at the point the hook was invoked, profile
and user information, etc. It is recommeded you call this '$a' to match its usage
elsewhere.

$b can be called anything you like. This is information which is specific to the hook
currently being processed, and generally contains information that is being immediately
processed or acted on that you can use, display, or alter. Remember to declare it with
'&' if you wish to alter it.

**Modules**

Plugins/addons may also act as "modules" and intercept all page requests for a given URL path. In order for a plugin to act as a module it needs to define a function "plugin_name_module()" which takes no arguments and need not do anything.

If this function exists, you will now receive all page requests for "http://my.web.site/plugin_name" - with any number of URL components as additional arguments. These are parsed into an array $a->argv, with a corresponding $a->argc indicating the number of URL components. So http://my.web.site/plugin/arg1/arg2 would look for a module named "plugin" and pass its module functions the $a App structure (which is available to many components). This will include:
     $a->argc = 3
     $a->argv = array(0 => 'plugin', 1 => 'arg1', 2 => 'arg2');

Your module functions will often contain the function plugin_name_content(&$a), which defines and returns the page body content. They may also contain plugin_name_post(&$a) which is called before the _content function and typically handles the results of POST forms. You may also have plugin_name_init(&$a) which is called very early on and often does module initialisation. 



**Current hooks:**

**'authenticate'** - called when a user attempts to login.
    $b is an array
        'username' => the supplied username
        'password' => the supplied password
        'authenticated' => set this to non-zero to authenticate the user.
        'user_record' => successful authentication must also return a valid user record from the database

**'logged_in'** - called after a user has successfully logged in.
    $b contains the $a->user array


**'display_item'** - called when formatting a post for display.
    $b is an array
        'item' => The item (array) details pulled from the database
        'output' => the (string) HTML representation of this item prior to adding it to the page

**'post_local'** - called when a status post or comment is entered on the local system
    $b is the item array of the information to be stored in the database
        {Please note: body contents are bbcode - not HTML)

**'post_local_end'** - called when a local status post or comment has been stored on the local system
    $b is the item array of the information which has just been stored in the database
        {Please note: body contents are bbcode - not HTML)

**'post_remote'** - called when receiving a post from another source. This may also be used to post local activity or system generated messages.
    $b is the item array of information to be stored in the database and the item
    body is bbcode.

**'settings_form'** - called when generating the HTML for the user Settings page
    $b is the (string) HTML of the settings page before the final '</form>' tag.

**'settings_post'** - called when the Settings pages are submitted.
    $b is the $_POST array

**'plugin_settings'** - called when generating the HTML for the addon settings page
    $b is the (string) HTML of the addon settings page before the final '</form>' tag.

**'plugin_settings_post'** - called when the Addon Settings pages are submitted.
    $b is the $_POST array

**'profile_post'** - called when posting a profile page.
    $b is the $_POST array

**'profile_edit'** - called prior to output of profile edit page
    $b is array
        'profile' => profile (array) record from the database
        'entry' => the (string) HTML of the generated entry


**'profile_advanced'** - called when the HTML is generated for the 'Advanced profile', corresponding to the 'Profile' tab within a person's profile page.
    $b is the (string) HTML representation of the generated profile
    (The profile array details are in $a->profile)

**'directory_item'** - called from the Directory page when formatting an item for display
    $b is an array
        'contact' => contact (array) record for the person from the database
        'entry' => the (string) HTML of the generated entry

**'profile_sidebar_enter'** - called prior to generating the sidebar "short" profile for a page
    $b is (array) the person's profile array

**'profile_sidebar'** - called when generating the sidebar "short" profile for a page
    $b is an array
        'profile' => profile (array) record for the person from the database
        'entry' => the (string) HTML of the generated entry

**'contact_block_end'** - called when formatting the block of contacts/friends on a profile sidebar has completed
    $b is an array
          'contacts' => array of contacts
          'output' => the (string) generated HTML of the contact block

**'bbcode'** - called during conversion of bbcode to html
    $b is (string) converted text

**'html2bbcode'** - called during conversion of html to bbcode (e.g. remote message posting)
    $b is (string) converted text

**'page_header'** - called after building the page navigation section
    $b is (string) HTML of nav region

**'personal_xrd'** - called prior to output of personal XRD file.
    $b is an array
        'user' => the user record for the person
        'xml' => the complete XML to be output

**'home_content'** - called prior to output home page content, shown to unlogged users
    $b is (string) HTML of section region

**'contact_edit'** - called when editing contact details on an individual from the Contacts page
    $b is (array)
        'contact' => contact record (array) of target contact
        'output' => the (string) generated HTML of the contact edit page

**'contact_edit_post'** - called when posting the contact edit page
    $b is the $_POST array

**'init_1'** - called just after DB has been opened and before session start
    $b is not used or passed


**'page_end'** - called after HTML content functions have completed
    $b is (string) HTML of content div


*** = subject to change

Not yet documented (you may view these within the source code):

**'atom_feed'** ***

**'atom_feed_end'** ***

**'parse_atom'** ***

**'atom_author'** ***

**'atom_entry'** ***

A complete list of all hook callbacks with file locations (generated 22-Feb-2011): Please see the source for details of any hooks not documented above.

boot.php:       call_hooks('contact_block_end', $arr);

boot.php:       call_hooks('profile_sidebar_enter', $profile);

boot.php:       call_hooks('profile_sidebar', $arr);

boot.php:       call_hooks("proc_run", $args);

include/nav.php:        call_hooks('page_header', $a->page['nav']);

include/auth.php:               call_hooks('authenticate', $addon_auth);

include/auth.php:               call_hooks('logged_in', $a->user);

include/bbcode.php:     call_hooks('bbcode',$Text);

include/acl_selectors.php:      call_hooks($a->module . '_pre_' . $selname, $arr);

include/acl_selectors.php:      call_hooks($a->module . '_post_' . $selname, $o);

include/acl_selectors.php:      call_hooks($a->module . '_pre_' . $selname, $arr);

include/acl_selectors.php:      call_hooks($a->module . '_post_' . $selname, $o);

include/items.php:      call_hooks('atom_feed', $atom);

include/items.php:              call_hooks('atom_feed_end', $atom);

include/items.php:      call_hooks('atom_feed_end', $atom);

include/items.php:      call_hooks('parse_atom', $arr);

include/items.php:      call_hooks('post_remote',$arr);

include/items.php:      call_hooks('atom_author', $o);

include/items.php:      call_hooks('atom_entry', $o);

include/html2bbcode.php:        call_hooks('html2bbcode', $text);

index.php:      call_hooks('init_1');

index.php:call_hooks('app_menu', $arr);

index.php:call_hooks('page_end', $a->page['content']);

mod/photos.php: call_hooks('photo_post_init', $_POST);

mod/photos.php: call_hooks('photo_post_file',$ret);

mod/photos.php: call_hooks('photo_post_end',intval($item_id));

mod/photos.php:         call_hooks('photo_upload_form',$ret);

mod/parse_url.php:      call_hooks('parse_link', $arr);

mod/home.php:   call_hooks("home_content",$o);

mod/contacts.php:       call_hooks('contact_edit_post', $_POST);

mod/contacts.php:               call_hooks('contact_edit', $arr);

mod/settings.php:               call_hooks('plugin_settings_post', $_POST);

mod/settings.php:       call_hooks('settings_post', $_POST);

mod/settings.php:               call_hooks('plugin_settings', $o);

mod/settings.php:       call_hooks('settings_form',$o);

mod/network.php:                call_hooks('jot_tool', $jotplugins);

mod/network.php:                call_hooks('jot_networks', $jotnets);

mod/network.php:                        call_hooks('display_item', $arr);

mod/xrd.php:    call_hooks('personal_xrd', $arr);

mod/item.php:   call_hooks('post_local_start', $_POST);

mod/item.php:   call_hooks('post_local',$datarray);

mod/item.php:   call_hooks('post_local_end', $datarray);

mod/profile.php:                        call_hooks('profile_advanced',$o);

mod/profile.php:                        call_hooks('jot_tool', $jotplugins); 

mod/profile.php:                        call_hooks('jot_networks', $jotnets);

mod/profile.php:                        call_hooks('display_item', $arr);

mod/display.php:                        call_hooks('display_item', $arr);

mod/profiles.php:       call_hooks('profile_post', $_POST);

mod/profiles.php:               call_hooks('profile_edit', $arr);

mod/cb.php:     call_hooks('cb_init');

mod/cb.php:     call_hooks('cb_post', $_POST);

mod/cb.php:     call_hooks('cb_afterpost');

mod/cb.php:     call_hooks('cb_content', $o);

mod/directory.php:                      call_hooks('directory_item', $arr);
