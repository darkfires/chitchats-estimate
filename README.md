NOTE: This class requires the PHP extensions tidy and curl.

To use this you need to get a Session cookie and CSRF Token.
So login to Chit Chats dashboard, click on the left Shipping Calculator. Fill out the form details, before you click Calculate Rate, right click on the page
and click Inspect.  This opens Developer Tools, you will then want to click on the Network Tab, put a checkbox on "Preserve log" then click Calculate Rate.
You will then have a bunch of entries listed and need to find the one that says "estimate", then on the right click on Headers.
Look for Request Headers, then scroll down to find Cookie and X-Csrf-Token.  You need to copy the Cookie and the X-Csrf-Token into the variables in the PHP class.
You also need your client ID from the URL https://chitchats.com/clients/XXXXXX/estimate when creating a new Estimate object.

The Chit Chats API does not provide an estimate function and the only way to get rates fills up your dashboard with false shipments that then need to be deleted,
so this addresses that need.  Unfortunately there's no way to automate getting new session cookies because the Chit Chats signin is protected by reCAPTCHA and their API key
doesn't work with estimate.

There is chitchats_crontab.php you can stick in crontab to keep the session alive to prevent it from expiring.

Usage:

try {
	// Estimate ( Chit Chats Client ID )
	$estimate = new Estimate(XXXXXX);

	$rates = $estimate->getRates(array(
		"estimate_view_model[package_preset]"   => "custom",
		"estimate_view_model[region_id]"	=> "1",
		"estimate_view_model[country_code]"     => "US",
		"estimate_view_model[postal_code]"      => "11228",
		"estimate_view_model[street]"		=> "123 ANYWHERE ST",
		"estimate_view_model[city]"		=> "Brooklyn",
		"estimate_view_model[province_code]"	=> "NY",
		"estimate_view_model[package_type]"	=> "parcel",	// thick_envelope, letter
		"estimate_view_model[size_x_amount]"	=> "30",	// length
		"estimate_view_model[size_y_amount]"	=> "30",	// width
		"estimate_view_model[size_z_amount]"	=> "10",	// height
		"estimate_view_model[size_unit]"	=> "cm",
		"estimate_view_model[weight_amount]"	=> "0.3",
		"estimate_view_model[weight_unit]"	=> "kg"
		));

	echo print_r($rates, 1);
} catch (Exception $e) {
	echo "Exception error: {$e->getMessage()}\n";
}
