<?php

namespace MediaWiki\ParamValidator\TypeDef;

use ExternalUserNames;
// phpcs:ignore MediaWiki.Classes.UnusedUseStatement.UnusedUse
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserIdentityValue;
use Title;
use User;
use Wikimedia\IPUtils;
use Wikimedia\Message\MessageValue;
use Wikimedia\ParamValidator\ParamValidator;
use Wikimedia\ParamValidator\TypeDef;

/**
 * Type definition for user types
 *
 * Failure codes:
 *  - 'baduser': The value was not a valid MediaWiki user. No data.
 *
 * @since 1.35
 */
class UserDef extends TypeDef {

	/**
	 * (string[]) Allowed types of user.
	 *
	 * One or more of the following values:
	 * - 'name': User names are allowed.
	 * - 'ip': IP ("anon") usernames are allowed.
	 * - 'cidr': IP ranges are allowed.
	 * - 'interwiki': Interwiki usernames are allowed.
	 * - 'id': Allow specifying user IDs, formatted like "#123".
	 *
	 * Default is `[ 'name', 'ip', 'cidr', 'interwiki' ]`.
	 *
	 * Avoid combining 'id' with PARAM_ISMULTI, as it may result in excessive
	 * DB lookups. If you do combine them, consider setting low values for
	 * PARAM_ISMULTI_LIMIT1 and PARAM_ISMULTI_LIMIT2 to mitigate it.
	 */
	public const PARAM_ALLOWED_USER_TYPES = 'param-allowed-user-types';

	/**
	 * (bool) Whether to return a UserIdentity object.
	 *
	 * If false, the validated user name is returned as a string. Default is false.
	 *
	 * Avoid setting true with PARAM_ISMULTI, as it may result in excessive DB
	 * lookups. If you do combine them, consider setting low values for
	 * PARAM_ISMULTI_LIMIT1 and PARAM_ISMULTI_LIMIT2 to mitigate it.
	 */
	public const PARAM_RETURN_OBJECT = 'param-return-object';

	public function validate( $name, $value, array $settings, array $options ) {
		list( $type, $user ) = $this->processUser( $value );

		if ( !$user || !in_array( $type, $settings[self::PARAM_ALLOWED_USER_TYPES], true ) ) {
			$this->failure( 'baduser', $name, $value, $settings, $options );
		}
		return empty( $settings[self::PARAM_RETURN_OBJECT] ) ? $user->getName() : $user;
	}

	public function normalizeSettings( array $settings ) {
		if ( isset( $settings[self::PARAM_ALLOWED_USER_TYPES] ) ) {
			$settings[self::PARAM_ALLOWED_USER_TYPES] = array_values( array_intersect(
				[ 'name', 'ip', 'cidr', 'interwiki', 'id' ],
				$settings[self::PARAM_ALLOWED_USER_TYPES]
			) );
		}
		if ( empty( $settings[self::PARAM_ALLOWED_USER_TYPES] ) ) {
			$settings[self::PARAM_ALLOWED_USER_TYPES] = [ 'name', 'ip', 'cidr', 'interwiki' ];
		}

		return parent::normalizeSettings( $settings );
	}

	/**
	 * Process $value to a UserIdentity, if possible
	 * @param string $value
	 * @return array [ string $type, UserIdentity|null $user ]
	 * @phan-return array{0:string,1:UserIdentity|null}
	 */
	private function processUser( string $value ) : array {
		// A user ID?
		if ( preg_match( '/^#(\d+)$/D', $value, $m ) ) {
			return [ 'id', User::newFromId( $m[1] ) ];
		}

		// An interwiki username?
		if ( ExternalUserNames::isExternal( $value ) ) {
			$name = User::getCanonicalName( $value, false );
			return [
				'interwiki',
				is_string( $name ) ? new UserIdentityValue( 0, $value, 0 ) : null
			];
		}

		// A valid user name?
		$user = User::newFromName( $value, 'valid' );
		if ( $user ) {
			return [ 'name', $user ];
		}

		// (T232672) Reproduce the normalization applied in User::getCanonicalName() when
		// performing the checks below.
		if ( strpos( $value, '#' ) !== false ) {
			return [ '', null ];
		}
		$t = Title::newFromText( $value ); // In case of explicit "User:" prefix, sigh.
		if ( !$t || $t->getNamespace() !== NS_USER || $t->isExternal() ) { // likely
			$t = Title::newFromText( "User:$value" );
		}
		if ( !$t || $t->getNamespace() !== NS_USER || $t->isExternal() ) {
			// If it wasn't a valid User-namespace title, fail.
			return [ '', null ];
		}
		$value = $t->getText();

		// An IP?
		$b = IPUtils::RE_IP_BYTE;
		if ( IPUtils::isValid( $value ) ||
			// See comment for User::isIP.  We don't just call that function
			// here because it also returns true for things like
			// 300.300.300.300 that are neither valid usernames nor valid IP
			// addresses.
			preg_match( "/^$b\.$b\.$b\.xxx$/D", $value )
		) {
			return [ 'ip', new UserIdentityValue( 0, IPUtils::sanitizeIP( $value ), 0 ) ];
		}

		// A range?
		if ( IPUtils::isValidRange( $value ) ) {
			return [ 'cidr', new UserIdentityValue( 0, IPUtils::sanitizeIP( $value ), 0 ) ];
		}

		// Fail.
		return [ '', null ];
	}

	public function getParamInfo( $name, array $settings, array $options ) {
		$info = parent::getParamInfo( $name, $settings, $options );

		$info['subtypes'] = $settings[self::PARAM_ALLOWED_USER_TYPES];

		return $info;
	}

	public function getHelpInfo( $name, array $settings, array $options ) {
		$info = parent::getParamInfo( $name, $settings, $options );

		$isMulti = !empty( $settings[ParamValidator::PARAM_ISMULTI] );

		$subtypes = [];
		foreach ( $settings[self::PARAM_ALLOWED_USER_TYPES] as $st ) {
			// Messages: paramvalidator-help-type-user-subtype-name,
			// paramvalidator-help-type-user-subtype-ip, paramvalidator-help-type-user-subtype-cidr,
			// paramvalidator-help-type-user-subtype-interwiki, paramvalidator-help-type-user-subtype-id
			$subtypes[] = MessageValue::new( "paramvalidator-help-type-user-subtype-$st" );
		}
		$info[ParamValidator::PARAM_TYPE] = MessageValue::new( 'paramvalidator-help-type-user' )
			->params( $isMulti ? 2 : 1 )
			->textListParams( $subtypes )
			->numParams( count( $subtypes ) );

		return $info;
	}

}