<?php
declare(strict_types=1);
if ( PHP_SAPI !== 'cli' ) {
	exit;
}
require dirname( __DIR__ ) . '/vendor/autoload.php';
use UWCMP\Application\Services\Moderator;
use UWCMP\Domain\Content\ContentField;
use UWCMP\Domain\Content\ContentPayload;
use UWCMP\Domain\Content\ModerationContext;
use UWCMP\Domain\Detection\Term;
use UWCMP\Domain\Moderation\ModerationRequest;
use UWCMP\Domain\Policy\Policy;

$report = [ 'environment' => [ 'php' => PHP_VERSION, 'os' => PHP_OS_FAMILY, 'architecture' => php_uname( 'm' ), 'intl' => INTL_ICU_VERSION, 'samples' => 100, 'scope' => 'In-memory engine. Zero SQL/network calls; compilation separate. Synthetic shared-prefix dictionary; not full WordPress request latency.' ], 'results' => [] ];
foreach ( [ 100, 1000, 10000, 100000 ] as $count ) {
	$terms = [];
	for ( $i = 0; $i < $count; ++$i ) {
		$terms[] = new Term( 't-' . $i, 'prohibited' . str_pad( (string) $i, 6, '0', STR_PAD_LEFT ) );
	}
	$before = memory_get_usage( true );
	$start = hrtime( true );
	$engine = new Moderator( new Policy( 1, $terms, [] ) );
	$compile = ( hrtime( true ) - $start ) / 1e6;
	$memory = memory_get_usage( true ) - $before;
	foreach ( [ 100, 1024, 10240, 102400 ] as $bytes ) {
		foreach ( [ 'clean', 'hit' ] as $case ) {
			$text = substr( str_repeat( 'ordinary safe writing ', (int) ceil( $bytes / 22 ) ), 0, $bytes );
			if ( $case === 'hit' ) {
				$text = substr( $text, 0, -18 ) . ' prohibited000000 ';
			}
			$request = new ModerationRequest( new ContentPayload( [ new ContentField( 'body', $text ) ] ), new ModerationContext( 'post', 'benchmark' ) );
			for ( $warm = 0; $warm < 5; ++$warm ) {
				$engine->check( $request );
			}
			$times = [];
			for ( $sample = 0; $sample < 100; ++$sample ) {
				$start = hrtime( true );
				$engine->check( $request );
				$times[] = ( hrtime( true ) - $start ) / 1e6;
			}
			sort( $times );
			// Deliberately bad historical baseline: one scan per dictionary entry.
			$baseline = [];
			for ( $sample = 0; $sample < 5; ++$sample ) {
				$start = hrtime( true );
				foreach ( $terms as $term ) {
					strpos( $text, $term->text );
				}
				$baseline[] = ( hrtime( true ) - $start ) / 1e6;
			}
			sort( $baseline );
			$report['results'][] = [ 'terms' => $count, 'input_bytes' => strlen( $text ), 'case' => $case, 'compile_ms' => round( $compile, 3 ), 'allocated_index_bytes' => $memory, 'p50_ms' => round( $times[49], 3 ), 'p95_ms' => round( $times[94], 3 ), 'p99_ms' => round( $times[98], 3 ), 'linear_scan_p50_ms' => round( $baseline[2], 3 ), 'query_count' => 0 ];
		}
	}
	unset( $engine, $terms );
	gc_collect_cycles();
}
$report['peak_memory_bytes'] = memory_get_peak_usage( true );
$json = json_encode( $report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR );
if ( in_array( '--write', $argv, true ) ) {
	file_put_contents( dirname( __DIR__ ) . '/docs/benchmark-results.json', $json . "\n" );
}
echo $json . "\n";
