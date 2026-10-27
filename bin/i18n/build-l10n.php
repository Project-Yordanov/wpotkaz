<?php
// Usage: php build-l10n.php <strings.json> <bg.php> <languages_dir> <version>
// The .pot ships in <languages_dir>; the bg_BG files go to bin/i18n/build/ (not shipped –
// WordPress.org serves translations from translate.wordpress.org; import the .po there).
list( , $json, $bgfile, $outdir, $version ) = $argv;
$bgdir = __DIR__ . '/build';
$strings = json_decode( file_get_contents( $json ), true );
foreach ( array( $outdir, $bgdir ) as $dir ) {
	if ( ! is_dir( $dir ) && ! mkdir( $dir, 0777, true ) ) {
		fwrite( STDERR, "Cannot create $dir\n" );
		exit( 1 );
	}
}
$write = function ( $file, $data ) {
	if ( false === file_put_contents( $file, $data ) ) {
		fwrite( STDERR, "Cannot write $file\n" );
		exit( 1 );
	}
};
$bg      = require $bgfile;
$errors  = 0;

// Mixed-script check (Latin letter adjacent to Cyrillic) in translations.
foreach ( $bg as $k => $v ) {
	foreach ( (array) $v as $t ) {
		if ( preg_match( '/\p{Cyrillic}[A-Za-z]|[A-Za-z]\p{Cyrillic}/u', $t ) ) {
			fwrite( STDERR, "Mixed script: $t\n" );
			$errors++;
		}
	}
}

// Completeness + placeholders.
$ph = function ( $s ) {
	preg_match_all( '/%(\d+\$)?[sd]|\{[a-z_]+\}/', $s, $m );
	$r = $m[0];
	sort( $r );
	return $r;
};
foreach ( $strings as $key => $e ) {
	if ( ! isset( $bg[ $key ] ) ) {
		fwrite( STDERR, 'Missing: ' . str_replace( "\4", ' | ', $key ) . "\n" );
		$errors++;
		continue;
	}
	$tr = (array) $bg[ $key ];
	foreach ( $tr as $t ) {
		$a = $ph( $e['s'] );
		$b = $ph( $t );
		// Unnumbered %s/%d can be reordered only if numbered – compare as multisets.
		if ( $a !== $b ) {
			fwrite( STDERR, 'Placeholder mismatch: ' . $e['s'] . ' => ' . $t . "\n" );
			$errors++;
		}
	}
}
foreach ( $bg as $key => $v ) {
	if ( ! isset( $strings[ $key ] ) ) {
		fwrite( STDERR, 'Unused translation: ' . str_replace( "\4", ' | ', $key ) . "\n" );
	}
}
if ( $errors ) {
	fwrite( STDERR, "$errors error(s)\n" );
	exit( 1 );
}

$esc = function ( $s ) {
	return '"' . str_replace( array( '\\', '"', "\n", "\t" ), array( '\\\\', '\\"', '\\n', '\\t' ), $s ) . '"';
};
$date = gmdate( 'Y-m-d H:iO' );

$header = function ( $lang ) use ( $version, $date ) {
	$h  = "Project-Id-Version: OtkazPro $version\n";
	$h .= "Report-Msgid-Bugs-To: \n";
	$h .= "POT-Creation-Date: $date\n";
	$h .= 'PO-Revision-Date: ' . ( $lang ? $date : 'YEAR-MO-DA HO:MI+ZONE' ) . "\n";
	$h .= 'Last-Translator: ' . ( $lang ? 'Project Yordanov' : 'FULL NAME <EMAIL@ADDRESS>' ) . "\n";
	$h .= 'Language-Team: ' . ( $lang ? 'Bulgarian' : 'LANGUAGE <LL@li.org>' ) . "\n";
	if ( $lang ) {
		$h .= "Language: $lang\n";
	}
	$h .= "MIME-Version: 1.0\nContent-Type: text/plain; charset=UTF-8\nContent-Transfer-Encoding: 8bit\n";
	$h .= 'Plural-Forms: ' . ( $lang ? 'nplurals=2; plural=(n != 1);' : 'nplurals=INTEGER; plural=EXPRESSION;' ) . "\n";
	$h .= "X-Domain: otkazpro\n";
	return $h;
};

$po = function ( $lang ) use ( $strings, $bg, $esc, $header ) {
	$out  = "# Copyright (C) Project Yordanov\n# This file is distributed under the GPL-2.0-or-later.\n";
	$out .= "msgid \"\"\nmsgstr \"\"\n";
	foreach ( explode( "\n", rtrim( $header( $lang ) ) ) as $line ) {
		$out .= $esc( $line . "\n" ) . "\n";
	}
	foreach ( $strings as $key => $e ) {
		$out .= "\n";
		if ( $e['comment'] ) {
			$out .= '#. ' . $e['comment'] . "\n";
		}
		foreach ( array_slice( $e['refs'], 0, 5 ) as $ref ) {
			$out .= "#: $ref\n";
		}
		if ( preg_match( '/%(\d+\$)?[sd]/', $e['s'] ) ) {
			$out .= "#, php-format\n";
		}
		if ( $e['c'] ) {
			$out .= 'msgctxt ' . $esc( $e['c'] ) . "\n";
		}
		$out .= 'msgid ' . $esc( $e['s'] ) . "\n";
		$tr = $lang ? (array) $bg[ $key ] : array();
		if ( $e['p'] ) {
			$out .= 'msgid_plural ' . $esc( $e['p'] ) . "\n";
			$out .= 'msgstr[0] ' . $esc( $tr[0] ?? '' ) . "\n";
			$out .= 'msgstr[1] ' . $esc( $tr[1] ?? '' ) . "\n";
		} else {
			$out .= 'msgstr ' . $esc( $tr[0] ?? '' ) . "\n";
		}
	}
	return $out;
};

$write( "$outdir/otkazpro.pot", $po( '' ) );
$write( "$bgdir/otkazpro-bg_BG.po", $po( 'bg_BG' ) );

// .mo
$entries = array( '' => $header( 'bg_BG' ) );
foreach ( $strings as $key => $e ) {
	$id = $e['s'];
	if ( $e['p'] ) {
		$id .= "\0" . $e['p'];
	}
	if ( $e['c'] ) {
		$id = $e['c'] . "\4" . $id;
	}
	$entries[ $id ] = implode( "\0", (array) $bg[ $key ] );
}
ksort( $entries, SORT_STRING );
$n      = count( $entries );
$ids    = '';
$strs   = '';
$offs   = array();
foreach ( $entries as $id => $str ) {
	$offs[] = array( strlen( $id ), strlen( $ids ), strlen( $str ), strlen( $strs ) );
	$ids   .= $id . "\0";
	$strs  .= $str . "\0";
}
$key_start = 28 + 16 * $n;
$val_start = $key_start + strlen( $ids );
$mo        = pack( 'Iiiiiii', 0x950412de, 0, $n, 28, 28 + 8 * $n, 0, $key_start );
foreach ( $offs as $o ) {
	$mo .= pack( 'ii', $o[0], $key_start + $o[1] );
}
foreach ( $offs as $o ) {
	$mo .= pack( 'ii', $o[2], $val_start + $o[3] );
}
$mo .= $ids . $strs;
$write( "$bgdir/otkazpro-bg_BG.mo", $mo );

// .l10n.php (WordPress 6.5+ performant translations).
$messages = array();
foreach ( $entries as $id => $str ) {
	if ( '' === $id ) {
		continue;
	}
	// Plural msgid key in l10n.php is the singular only.
	$parts = explode( "\0", $id );
	$messages[ $parts[0] ] = $str;
}
$php  = "<?php\nreturn " . var_export(
	array(
		'project-id-version' => "OtkazPro $version",
		'domain'             => 'otkazpro',
		'language'           => 'bg_BG',
		'plural-forms'       => 'nplurals=2; plural=(n != 1);',
		'messages'           => $messages,
	),
	true
) . ";\n";
$write( "$bgdir/otkazpro-bg_BG.l10n.php", $php );

echo count( $strings ) . " strings OK → pot, po, mo, l10n.php\n";
