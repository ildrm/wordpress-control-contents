<?php
declare(strict_types=1);
if ( PHP_SAPI !== 'cli' ) {
	exit;
}
require dirname( __DIR__ ) . '/vendor/autoload.php';
use UWCMP\Application\Services\Normalizer;
use UWCMP\Application\Services\TermDetector;
use UWCMP\Domain\Detection\Term;

$fixtures = json_decode( file_get_contents( dirname( __DIR__ ) . '/tests/fixtures/evaluation.json' ), true, 32, JSON_THROW_ON_ERROR );
$normalizer = new Normalizer();
$detector = new TermDetector( array_map( static fn( array $t ): Term => new Term( $t['id'], $t['text'] ), $fixtures['terms'] ), $normalizer );
$counts = [];
$errors = [];
foreach ( $fixtures['samples'] as $id => $sample ) {
	$found = $detector->detect( $normalizer->normalize( $sample['text'] ), 'body', 'comment' )->evidence !== [];
	foreach ( [ $sample['language'], 'all' ] as $language ) {
		$counts[ $language ] ??= [ 'tp' => 0, 'tn' => 0, 'fp' => 0, 'fn' => 0 ];
		$key = $found ? ( $sample['violation'] ? 'tp' : 'fp' ) : ( $sample['violation'] ? 'fn' : 'tn' );
		++$counts[ $language ][ $key ];
	}
	if ( $found !== $sample['violation'] ) {
		$errors[] = $id;
	}
}
$metrics = [];
foreach ( $counts as $language => $c ) {
	$precision = ( $c['tp'] + $c['fp'] ) > 0 ? $c['tp'] / ( $c['tp'] + $c['fp'] ) : null;
	$recall = ( $c['tp'] + $c['fn'] ) > 0 ? $c['tp'] / ( $c['tp'] + $c['fn'] ) : null;
	$metrics[ $language ] = $c + [ 'precision' => $precision, 'recall' => $recall, 'f1' => $precision !== null && $recall !== null && $precision + $recall > 0 ? 2 * $precision * $recall / ( $precision + $recall ) : null, 'fpr' => $c['tn'] + $c['fp'] > 0 ? $c['fp'] / ( $c['tn'] + $c['fp'] ) : null, 'fnr' => $c['tp'] + $c['fn'] > 0 ? $c['fn'] / ( $c['tp'] + $c['fn'] ) : null ];
}
$report = [ 'scope' => $fixtures['description'], 'production_accuracy_established' => false, 'hard_block_release_gate' => 'NOT SATISFIED: no representative validation corpus', 'metrics' => $metrics, 'failed_sample_ids' => $errors ];
$json = json_encode( $report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR );
if ( in_array( '--write', $argv, true ) ) {
	file_put_contents( dirname( __DIR__ ) . '/docs/accuracy-results.json', $json . "\n" );
}
echo $json . "\n";
exit( $errors === [] ? 0 : 1 );
