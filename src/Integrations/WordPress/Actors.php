<?php
declare(strict_types=1);
namespace UWCMP\Integrations\WordPress;

use UWCMP\Domain\Content\Actor;

final class Actors {
	public static function from_user_id( int $id ): Actor {
		$user       = $id > 0 ? get_userdata( $id ) : false;
		$registered = $user ? strtotime( $user->user_registered . ' UTC' ) : false;
		return new Actor( $id, $user ? array_values( $user->roles ) : array(), $registered === false ? null : max( 0, time() - $registered ) );
	}
}
