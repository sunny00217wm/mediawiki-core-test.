<?php

namespace MediaWiki\ParamValidator\TypeDef;

use ApiResult;
use MediaWiki\MediaWikiServices;
use Wikimedia\Message\DataMessageValue;
use Wikimedia\ParamValidator\ParamValidator;
use Wikimedia\ParamValidator\SimpleCallbacks;
use Wikimedia\ParamValidator\TypeDef\TypeDefTestCase;
use Wikimedia\ParamValidator\ValidationException;

/**
 * @covers MediaWiki\ParamValidator\TypeDef\NamespaceDef
 */
class NamespaceDefTest extends TypeDefTestCase {

	protected static $testClass = NamespaceDef::class;

	protected function getInstance( SimpleCallbacks $callbacks, array $options ) {
		return new static::$testClass(
			$callbacks,
			MediaWikiServices::getInstance()->getNamespaceInfo()
		);
	}

	private static function getNamespaces( $extra = [] ) {
		$namespaces = array_merge(
			MediaWikiServices::getInstance()->getNamespaceInfo()->getValidNamespaces(),
			$extra
		);
		sort( $namespaces );
		return $namespaces;
	}

	public function provideValidate() {
		$settings = [
			ParamValidator::PARAM_TYPE => 'namespace',
		];
		$extraSettings = [
			ParamValidator::PARAM_TYPE => 'namespace',
			NamespaceDef::PARAM_EXTRA_NAMESPACES => [ -5 ],
		];

		return [
			'Basic' => [ '0', 0, $settings ],
			'Bad namespace' => [
				'x',
				new ValidationException(
					DataMessageValue::new( 'paramvalidator-badvalue', [], 'badvalue', [] ), 'test', 'x', $settings
				),
				$settings
			],
			'Unknown namespace' => [
				'x',
				new ValidationException(
					DataMessageValue::new( 'paramvalidator-badvalue', [], 'badvalue', [] ), 'test', '-1', []
				),
			],
			'Extra namespaces' => [ '-5', -5, $extraSettings ],
		];
	}

	public function provideGetEnumValues() {
		return [
			'Basic test' => [
				[ ParamValidator::PARAM_TYPE => 'namespace' ],
				self::getNamespaces(),
			],
			'Extra namespaces' => [
				[
					ParamValidator::PARAM_TYPE => 'namespace',
					NamespaceDef::PARAM_EXTRA_NAMESPACES => [ NS_SPECIAL, NS_MEDIA ]
				],
				self::getNamespaces( [ NS_SPECIAL, NS_MEDIA ] ),
			],
		];
	}

	public function provideNormalizeSettings() {
		return [
			'Basic test' => [ [], [] ],
			'Add PARAM_ALL' => [
				[ ParamValidator::PARAM_ISMULTI => true ],
				[ ParamValidator::PARAM_ISMULTI => true, ParamValidator::PARAM_ALL => true ],
			],
			'Force PARAM_ALL' => [
				[ ParamValidator::PARAM_ISMULTI => true, ParamValidator::PARAM_ALL => false ],
				[ ParamValidator::PARAM_ISMULTI => true, ParamValidator::PARAM_ALL => true ],
			],
			'Force PARAM_ALL (2)' => [
				[ ParamValidator::PARAM_ISMULTI => true, ParamValidator::PARAM_ALL => 'all' ],
				[ ParamValidator::PARAM_ISMULTI => true, ParamValidator::PARAM_ALL => true ],
			],
		];
	}

	public function provideStringifyValue() {
		return [
			'Basic test' => [ 123, '123' ],
			'Array' => [ [ 1, 2, 3 ], '1|2|3' ],
		];
	}

	public function provideGetInfo() {
		yield 'Basic test' => [
			[],
			[ 'type' => 'namespace' ],
			[
				// phpcs:ignore Generic.Files.LineLength.TooLong
				ParamValidator::PARAM_TYPE => '<message key="paramvalidator-help-type-enum"><text>1</text><list listType="comma"><text>0</text><text>1</text><text>2</text><text>3</text><text>4</text><text>5</text><text>6</text><text>7</text><text>8</text><text>9</text><text>10</text><text>11</text><text>12</text><text>13</text><text>14</text><text>15</text></list><num>16</num></message>',
				ParamValidator::PARAM_ISMULTI => null,
			],
		];

		yield 'Extra namespaces' => [
			[
				ParamValidator::PARAM_DEFAULT => 0,
				NamespaceDef::PARAM_EXTRA_NAMESPACES => [ NS_SPECIAL, NS_MEDIA ]
			],
			[ 'type' => 'namespace', 'extranamespaces' => [ NS_SPECIAL, NS_MEDIA ] ],
			[
				// phpcs:ignore Generic.Files.LineLength.TooLong
				ParamValidator::PARAM_TYPE => '<message key="paramvalidator-help-type-enum"><text>1</text><list listType="comma"><text>-1</text><text>-2</text><text>0</text><text>1</text><text>2</text><text>3</text><text>4</text><text>5</text><text>6</text><text>7</text><text>8</text><text>9</text><text>10</text><text>11</text><text>12</text><text>13</text><text>14</text><text>15</text></list><num>18</num></message>',
				ParamValidator::PARAM_ISMULTI => null,
			],
		];

		yield 'Extra namespaces, for Action API' => [
			[ NamespaceDef::PARAM_EXTRA_NAMESPACES => [ NS_SPECIAL, NS_MEDIA ] ],
			[
				'type' => 'namespace',
				'extranamespaces' => [
					NS_SPECIAL, NS_MEDIA,
					ApiResult::META_INDEXED_TAG_NAME => 'ns',
				],
			],
			[
				// phpcs:ignore Generic.Files.LineLength.TooLong
				ParamValidator::PARAM_TYPE => '<message key="paramvalidator-help-type-enum"><text>1</text><list listType="comma"><text>-1</text><text>-2</text><text>0</text><text>1</text><text>2</text><text>3</text><text>4</text><text>5</text><text>6</text><text>7</text><text>8</text><text>9</text><text>10</text><text>11</text><text>12</text><text>13</text><text>14</text><text>15</text></list><num>18</num></message>',
				ParamValidator::PARAM_ISMULTI => null,
			],
			[ 'module' => (object)[] ],
		];
	}

}