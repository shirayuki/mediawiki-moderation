<?php

/*
	Extension:Moderation - MediaWiki extension.
	Copyright (C) 2020 Edward Chernenko.

	This program is free software; you can redistribute it and/or modify
	it under the terms of the GNU General Public License as published by
	the Free Software Foundation; either version 3 of the License, or
	(at your option) any later version.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.
*/

/**
 * @file
 * Unit test of SpecialModeration.
 */

use MediaWiki\Moderation\ActionFactory;

require_once __DIR__ . "/autoload.php";

/**
 * @group Database
 */
class SpecialModerationTest extends ModerationUnitTestCase {
	protected $tablesUsed = [ 'moderation', 'moderation_block' ];

	/**
	 * Verify that Special:Moderation will throw PermissionsError for non-moderators.
	 * @covers SpecialModeration
	 */
	public function testNotPermitted() {
		$notModerator = self::getTestUser()->getUser();

		$mock = $this->addMockedAction( 'makesandwich' );
		$mock->expects( $this->never() )->method( 'execute' );

		$this->expectExceptionObject( new PermissionsError( 'moderation' ) );
		ModerationTestUtil::runSpecialModeration( $notModerator, [] );
	}

	/**
	 * Verify that Special:Moderation will throw sessionfailure error if needed token is incorrect.
	 * @param string|null $token Value of token= parameter (if any) or null.
	 * @dataProvider dataProviderSessionFailure
	 *
	 * @covers SpecialModeration
	 */
	public function testSessionFailure( $token ) {
		$moderator = self::getTestUser( [ 'moderator' ] )->getUser();
		$params = [ 'modaction' => 'makesandwich' ];
		if ( $token !== null ) {
			$params['token'] = $token;
		}

		$mock = $this->addMockedAction( $params['modaction'] );
		$mock->expects( $this->once() )->method( 'requiresEditToken' )->willReturn( true );
		$mock->expects( $this->never() )->method( 'execute' );

		$this->expectExceptionObject(
			new ErrorPageError( 'sessionfailure-title', 'sessionfailure' ) );

		ModerationTestUtil::runSpecialModeration( $moderator, $params );
	}

	/**
	 * Provide datasets for testSessionFailure() runs.
	 * @return array
	 */
	public function dataProviderSessionFailure() {
		return [
			'token needed, token NOT provided' => [ null ],
			'token needed, provided token is INCORRECT' => [ 'WRONG TOKEN' ]
		];
	}

	/**
	 * Verify that Special:Moderation?modaction=something runs execute() and then outputResult().
	 * @param bool $isTokenNeeded If true, tested ModerationAction will require an edit token.
	 * @dataProvider dataProviderSuccessfulAction
	 *
	 * @covers SpecialModeration
	 */
	public function testSuccessfulAction( $isTokenNeeded ) {
		$moderator = self::getTestUser( [ 'moderator' ] )->getUser();
		$params = [ 'modaction' => 'makesandwich' ];
		if ( $isTokenNeeded ) {
			$params['token'] = $moderator->getEditToken();
		}

		$mock = $this->addMockedAction( $params['modaction'] );
		$mock->expects( $this->once() )
			->method( 'requiresEditToken' )->willReturn( $isTokenNeeded );

		$mockedResult = [ 'cat' => 'feline', 'fennec fox' => 'canine' ];
		$mockedHtml = 'This is HTML that will be returned by outputResult()';

		$mock->expects( $this->once() )->method( 'execute' )->willReturn( $mockedResult );
		$mock->expects( $this->once() )->method( 'outputResult' )->with(
			// @phan-suppress-next-line PhanTypeMismatchArgument
			$this->identicalTo( $mockedResult ),
			// @phan-suppress-next-line PhanTypeMismatchArgument
			$this->isInstanceOf( OutputPage::class )
		)->will( $this->returnCallback(
			function ( $result, OutputPage $out ) use ( $mockedHtml ) {
				$out->addHTML( $mockedHtml );
			}
		) );

		$html = ModerationTestUtil::runSpecialModeration( $moderator, $params );
		$this->assertContains( $mockedHtml, $html );

		// TODO: assert that $html contains "return to Special:Moderation" link
	}

	/**
	 * Provide datasets for testSuccessfulAction() runs.
	 * @return array
	 */
	public function dataProviderSuccessfulAction() {
		return [
			'token needed, provided token is correct' => [ true ],
			'token NOT needed' => [ false ]
		];
	}

	/**
	 * Make a mock for ModerationAction class and make ActionFactory always return it.
	 * @param string $actionName
	 * @return \PHPUnit\Framework\MockObject\MockObject
	 */
	private function addMockedAction( $actionName ) {
		$actionMock = $this->getMockBuilder( ModerationAction::class )
			->disableOriginalConstructor()
			->disableProxyingToOriginalMethods()
			->setMethods( [ 'requiresEditToken', 'execute' ] )
			->getMockForAbstractClass();

		$factoryMock = $this->createMock( ActionFactory::class );
		$factoryMock->method( 'makeAction' )->will( $this->returnCallback(
			function ( IContextSource $context ) use ( $actionMock, $actionName ) {
				if ( $context->getRequest()->getVal( 'modaction' ) !== $actionName ) {
					throw new MWException(
						"This mocked ActionFactory only supports modaction=$actionName." );
				}

				// @phan-suppress-next-line PhanUndeclaredMethod
				$actionMock->setContext( $context );
				return $actionMock;
			}
		) );

		$this->setService( 'Moderation.ActionFactory', $factoryMock );
		return $actionMock;
	}
}
