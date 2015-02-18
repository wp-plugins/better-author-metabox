<div class="wrap">
<h2>Better Author Metabox</h2>

    <p>This plugin allows the Author metabox on post add and edit screens to include more users than WordPress includes by default.  Choose the post types where it should be overridden, and the roles of the users who should be included.</p>

<form method="post" action="options.php">
<?php 
	settings_fields(self::CONFIG);
	do_settings_sections( self::CONFIG );
	submit_button();
?>
</form>
</div>
