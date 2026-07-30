<?php
/**
 * Render the real alert email to _email-preview.html so it can be looked at in a browser.
 *
 * Preview only — it boots the TEST stubs, not WordPress, so a couple of things differ from a real
 * send and it is worth knowing which:
 *   - the site name is the stub's, not ABC's
 *   - the stub's add_query_arg() runs http_build_query(), which URL-encodes; real WordPress does
 *     not, so the quote link looks double-encoded here and is correct on a live site
 * The HTML, the boxes, the escaping and the logo are exactly what gets sent.
 *
 *   REPO=/path/to/abc-handoff /tmp/phpbin/php render_email_preview.php
 */

$repo = getenv( 'REPO' ) ?: dirname( __DIR__, 3 ) . '/Documents/GitHub/bh-violation-system/abc-handoff';
require $repo . '/wordpress-plugin/tests/wp-stubs.php';
require $repo . '/wordpress-plugin/abc-violation-lookup/includes/abc-vl-mail.php';

$GLOBALS['_mail']       = array();
$GLOBALS['_mail_fails'] = false;

update_option( 'abc_vl_logo_url', getenv( 'LOGO' ) ?: 'http://127.0.0.1:8791/abc-logo-email.png' );
update_option( 'abc_vl_phone', '(929) 581-3300' );

$sub = (object) array(
	'email'             => 'owner@example.com',
	'address'           => '609 East 12 Street, Brooklyn',
	'unsubscribe_token' => 'demo-token',
);

// Real HPD wording, including the § the dataset actually uses — the character that shows whether
// the whole pipeline is UTF-8 clean end to end.
$vios = array(
	array(
		'class' => 'C', 'apartment' => '6F', '_cat' => 'lead', 'newcorrectbydate' => '2026-08-14T00:00:00.000',
		'novdescription' => '§ 27-2056.6 ADM CODE REPAIR ALL PEELING LEAD-BASED PAINT AND ANY UNDERLYING DEFECTIVE SUBSURFACE IN THE ENTIRE APARTMENT LOCATED AT APT 6F, 6th STORY, 4th APARTMENT FROM NORTH AT EAST',
	),
	array(
		'class' => 'C', 'apartment' => '3R', '_cat' => 'lead', 'newcorrectbydate' => '2026-08-14T00:00:00.000',
		'novdescription' => '§ 27-2056.6 ADM CODE PROPERLY REPAIR WITH SIMILAR MATERIAL THE BROKEN OR DEFECTIVE PLASTERED SURFACES AND PAINT IN A UNIFORM COLOR AT CEILING AND WALLS IN THE BATHROOM',
	),
	array(
		'class' => 'B', 'apartment' => '6F', '_cat' => 'mold', 'newcorrectbydate' => '2026-09-01T00:00:00.000',
		'novdescription' => '§ 27-2005 HMC: TRACE AND REPAIR THE SOURCE AND ABATE THE NUISANCE CONSISTING OF MOLD APPROX. 8 SQ FT IN THE KITCHEN LOCATED AT APT 6F, 6th STORY',
	),
);

abc_vl_send_alert( $sub, $vios );
$m = end( $GLOBALS['_mail'] );

file_put_contents( __DIR__ . '/_email-preview.html', $m['body'] );
file_put_contents( __DIR__ . '/_email-preview.txt', $m['alt'] );

$checks = array(
	'subject'                 => $m['subject'],
	'content type'            => implode( ' | ', (array) $m['headers'] ),
	'html bytes'              => strlen( $m['body'] ),
	'plain-text alt bytes'    => strlen( $m['alt'] ),
	'unsubscribe in HTML'     => false !== strpos( $m['body'], 'demo-token' ) ? 'present' : 'MISSING',
	'unsubscribe in text'     => false !== strpos( $m['alt'], 'demo-token' ) ? 'present' : 'MISSING',
	'call-to-action buttons'  => substr_count( $m['body'], 'Get this cleared' ),
	'price leak'              => preg_match( '/\$\s?[\d,]+/', $m['body'] ) ? 'FOUND — BAD' : 'none',
	'section sign double-enc' => false !== strpos( $m['body'], "\xc3\x82\xc2\xa7" ) ? 'MOJIBAKE — BAD' : 'clean UTF-8',
	'valid UTF-8'             => mb_check_encoding( $m['body'], 'UTF-8' ) ? 'yes' : 'NO',
);
foreach ( $checks as $k => $v ) {
	printf( "  %-24s %s\n", $k, $v );
}
