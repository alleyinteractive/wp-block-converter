#!/usr/bin/env php
<?php
/**
 * Configure the PHP Package interactively.
 *
 * phpcs:disable
 */

if ( ! defined( 'STDIN' ) ) {
	die( 'Not in CLI mode.' );
}

if ( 0 === strpos( strtoupper( PHP_OS ), 'WIN' ) ) {
	die( 'Not supported in Windows. 🪟' );
}

function ask( string $question, string $default = '' ): string {
	$answer = readline(
		$question . ( $default ? " [{$default}]" : '' ) . ': '
	);

	return $answer ?: $default;
}

function confirm( string $question, bool $default = false ): bool {
	$answer = readline(
		"{$question} (yes/no) [" . ( $default ? 'yes' : 'no' ) . ']: '
	);

	if ( ! $answer ) {
		return $default;
	}

	return in_array( strtolower( trim( $answer ) ), [ 'y', 'yes', 'true', '1' ], true );
}

function writeln( string $line ): void {
	echo $line . PHP_EOL;
}

function run( string $command, string $dir = null ): string {
	$command = $dir ? "cd {$dir} && {$command}" : $command;

	return trim( shell_exec( $command ) );
}

function str_after( string $subject, string $search ): string {
	$pos = strrpos( $subject, $search );

	if ( $pos === false ) {
		return $subject;
	}

	return substr( $subject, $pos + strlen( $search ) );
}

function slugify( string $subject ): string {
	return strtolower( trim( preg_replace( '/[^A-Za-z0-9-]+/', '-', $subject ), '-' ) );
}

function title_case( string $subject ): string {
	return ensure_capitalp( str_replace( ' ', '_', ucwords( str_replace( [ '-', '_' ], ' ', $subject ) ) ) );
}

function ensure_capitalp( string $text ): string {
	return str_replace( 'Wordpress', 'WordPress', $text );
}

function replace_in_file( string $file, array $replacements ): void {
	$contents = file_get_contents( $file );

	file_put_contents(
		$file,
		str_replace(
			array_keys( $replacements ),
			array_values( $replacements ),
			$contents
		)
	);
}

function remove_readme_paragraphs( string $file ): void {
	$contents = file_get_contents( $file );

	file_put_contents(
		$file,
		trim( preg_replace( '/<!--delete-->.*<!--\/delete-->/s', '', $contents ) ?: $contents ),
	);
}

function determine_separator( string $path ): string {
	return str_replace( '/', DIRECTORY_SEPARATOR, $path );
}

function list_all_files_for_replacement(): array {
	return explode( PHP_EOL, run( 'grep -R -l ./  --exclude LICENSE --exclude configure.php --exclude composer.lock --exclude-dir .git --exclude-dir .github --exclude-dir vendor --exclude-dir bin --exclude-dir webpack --exclude-dir modules --exclude-dir .phpcs' ) );
}

function delete_files( string|array $paths ) {
	if ( ! is_array( $paths ) ) {
		$paths = [ $paths ];
	}

	foreach ( $paths as $path ) {
		$path = determine_separator( $path );

		if ( is_dir( $path ) ) {
			run( "rm -rf {$path}" );
		} elseif ( file_exists( $path ) ) {
			unlink( $path );
		}
	}
}

echo "\nWelcome friend to alleyinteractive/create-php-package! 😀\nLet's setup your PHP package 🚀\n\n";

$git_name    = run( 'git config user.name' );
$author_name = ask( 'Author name?', $git_name );

$git_email    = run( 'git config user.email' );
$author_email = ask( 'Author email?', $git_email );

$username_guess  = explode( ':', run( 'git config remote.origin.url' ) )[1] ?? '';
$username_guess  = dirname( $username_guess );
$username_guess  = basename( $username_guess );
$author_username = ask( 'Author username?', $username_guess );

$vendor_name      = ask( 'Vendor name (usually the Github Organization)?', $username_guess );
$vendor_slug      = slugify( $vendor_name );

$current_dir = getcwd();
$folder_name = ensure_capitalp( basename( $current_dir ) );

$package_name      = ask( 'Package name?', str_replace( '_', ' ', title_case( $folder_name ) ) );
$package_name_slug = slugify( $package_name );

$namespace  = ask( 'Package namespace?', title_case( $package_name ) );
$class_name = ask( 'Base class name for package?', title_case( $package_name ) );

$description = ask( 'Package description?', "This is my PHP package {$package_name}" );

writeln( '------' );
writeln( "Author      : {$author_name} ({$author_email})" );
writeln( "Vendor      : {$vendor_name} ({$vendor_slug})" );
writeln( "Package     : {$package_name} <{$package_name_slug}>" );
writeln( "Description : {$description}" );
writeln( "Namespace   : {$namespace}" );
writeln( "Main Class  : {$class_name}" );
writeln( '------' );

writeln( 'This script will replace the above values in all relevant files in the project directory.' );

if ( ! confirm( 'Modify files?', true ) ) {
	exit( 1 );
}

$search_and_replace = [
	'author_name'             => $author_name,
	'author_username'         => $author_username,
	'email@domain.com'        => $author_email,

	'A skeleton PHP package geared for WordPress Development' => $description,

	'Create_PHP_Package'       => $namespace,
	'Example_Package'          => $class_name,
	'package_name'             => $package_name,

	'create-php-package'      => $package_name_slug,
	'Create PHP Package'      => $package_name,

	'CREATE_PHP_PACKAGE'      => strtoupper( str_replace( '-', '_', $package_name ) ),
	'Skeleton'                => $class_name,
	'vendor_name'             => $vendor_name,
	'alleyinteractive'        => $vendor_slug,
];

foreach ( list_all_files_for_replacement() as $path ) {
	echo "Updating $path...\n";
	replace_in_file( $path, $search_and_replace );

	if ( str_contains( $path, determine_separator( 'src/class-example-package.php' ) ) ) {
		rename( $path, determine_separator( './src/class-' . str_replace( '_', '-', strtolower( $class_name ) ) . '.php' ) );
	}

	if ( str_contains( $path, 'README.md' ) ) {
		remove_readme_paragraphs( $path );
	}
}

echo "Done!\n\n";

if ( confirm( 'Do you want to run `composer install`?', true ) ) {
	if ( file_exists( __DIR__ . '/composer.lock' ) ) {
		echo run( 'composer update' );
	} else {
		echo run( 'composer install' );
	}

	echo "\n\n";
}

if (
	file_exists( __DIR__ . '/buddy.yml' ) && confirm( 'Do you need the Buddy CI configuration? (Alley devs only -- if the package is open-source it will not be needed)', false )
) {
	delete_files( [ '.buddy', 'buddy.yml' ] );
}

if ( confirm( 'Let this script delete itself?', true ) ) {
	delete_files(
		[
			'Makefile',
			__FILE__,
		]
	);
}

echo "\n\nWe're done! 🎉\n\n";

die( 0 );
