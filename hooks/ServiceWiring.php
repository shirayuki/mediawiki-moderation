<?php

/*
	Extension:Moderation - MediaWiki extension.
	Copyright (C) 2020-2021 Edward Chernenko.

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
 * Register services like ActionFactory in MediaWikiServices container.
 */

use MediaWiki\Config\ServiceOptions;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;
use MediaWiki\Moderation\ActionFactory;
use MediaWiki\Moderation\ActionLinkRenderer;
use MediaWiki\Moderation\ConsequenceManager;
use MediaWiki\Moderation\EditFormOptions;
use MediaWiki\Moderation\EntryFactory;
use MediaWiki\Moderation\Hook\HookRunner;
use MediaWiki\Moderation\RollbackResistantQuery;
use MediaWiki\Moderation\TimestampFormatter;

// @codeCoverageIgnoreStart
// (PHPUnit doesn't support @covers for out-of-class code)
// See also: T248172 "Allow static methods to be used for wiring"

return [
	'Moderation.ActionFactory' => static function ( MediaWikiServices $services ): ActionFactory {
		return new ActionFactory(
			$services->getService( 'Moderation.EntryFactory' ),
			$services->getService( 'Moderation.ConsequenceManager' )
		);
	},
	'Moderation.ActionLinkRenderer' => static function ( MediaWikiServices $services ): ActionLinkRenderer {
		return new ActionLinkRenderer(
			RequestContext::getMain(),
			$services->getLinkRenderer(),
			SpecialPage::getTitleFor( 'Moderation' )
		);
	},
	'Moderation.ApproveHook' => static function (): ModerationApproveHook {
		return new ModerationApproveHook(
			LoggerFactory::getInstance( 'ModerationApproveHook' )
		);
	},
	'Moderation.CanSkip' => static function ( MediaWikiServices $services ): ModerationCanSkip {
		return new ModerationCanSkip(
			new ServiceOptions(
				ModerationCanSkip::CONSTRUCTOR_OPTIONS,
				$services->getMainConfig()
			),
			$services->getService( 'Moderation.ApproveHook' )
		);
	},
	'Moderation.ConsequenceManager' => static function (): ConsequenceManager {
		return new ConsequenceManager();
	},
	'Moderation.EditFormOptions' => static function ( MediaWikiServices $services ): EditFormOptions {
		return new EditFormOptions(
			$services->getService( 'Moderation.ConsequenceManager' )
		);
	},
	'Moderation.EntryFactory' => static function ( MediaWikiServices $services ): EntryFactory {
		return new EntryFactory(
			$services->getLinkRenderer(),
			$services->getService( 'Moderation.ActionLinkRenderer' ),
			$services->getService( 'Moderation.TimestampFormatter' ),
			$services->getService( 'Moderation.ConsequenceManager' ),
			$services->getService( 'Moderation.CanSkip' ),
			$services->getService( 'Moderation.ApproveHook' )
		);
	},
	'Moderation.HookRunner' => static function ( MediaWikiServices $services ): HookRunner {
		return new HookRunner( $services->getHookContainer() );
	},
	'Moderation.NotifyModerator' =>
	static function ( MediaWikiServices $services ): ModerationNotifyModerator {
		return new ModerationNotifyModerator(
			$services->getLinkRenderer(),
			$services->getService( 'Moderation.EntryFactory' ),
			wfGetMainCache()
		);
	},
	'Moderation.Preload' => static function ( MediaWikiServices $services ): ModerationPreload {
		return new ModerationPreload(
			$services->getService( 'Moderation.EntryFactory' ),
			$services->getService( 'Moderation.ConsequenceManager' )
		);
	},
	'Moderation.RollbackResistantQuery' =>
	static function ( MediaWikiServices $services ): RollbackResistantQuery {
		return new RollbackResistantQuery(
			$services->getDBLoadBalancer()
		);
	},
	'Moderation.TimestampFormatter' => static function (): TimestampFormatter {
		return new TimestampFormatter();
	},
	'Moderation.VersionCheck' => static function ( MediaWikiServices $services ): ModerationVersionCheck {
		return new ModerationVersionCheck(
			new CachedBagOStuff( wfGetMainCache() ),
			$services->getDBLoadBalancer()
		);
	},
];

// @codeCoverageIgnoreEnd
