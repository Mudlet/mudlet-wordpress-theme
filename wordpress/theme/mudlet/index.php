<?php
/**
 * The fallback template.
 *
 * WordPress requires this file. Every route the site actually serves has a more
 * specific template, so this only runs for a request none of them claimed - in
 * which case the archive layout is the right shape.
 *
 * @package Mudlet
 */

defined( 'ABSPATH' ) || exit;

get_template_part( 'archive' );
