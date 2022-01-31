<?php namespace Bbt;

abstract class Aspectator
{
	public static $QUEUE_NAME = 'aspectator';

	public const ASPECT__ACTIVE_ACCOUNTS   = 'acc.act';
	public const ASPECT__PAYING_ACCOUNTS   = 'acc.pay';
	public const ASPECT__USER_ACTIONS      = 'usr.act';
	public const ASPECT__SESSIONS_START    = 'ses.sta';
	public const ASPECT__SIGNUP_ATTEMPT    = 'sig.att';
	public const ASPECT__SIGNUP_SUCCESSFUL = 'sig.suc';
	public const ASPECT__SIGNUP_BLOCKED    = 'sig.blk';
	public const ASPECT__ACCOUNT_UPGRADES  = 'acc.upg';
	public const ASPECT__PAYMENT_ATTEMPT   = 'pay.att';
	public const ASPECT__PAYMENT_FAIL      = 'pay.fal';
	public const ASPECT__PAYMENT_SUCCESS   = 'pay.suc';
	public const ASPECT__EMAIL_OPENS       = 'eml.opn';
	public const ASPECT__EMAIL_BOUNCES     = 'eml.bon';
	public const ASPECT__DEVICES           = 'dev';
	public const ASPECT__FRAUD_REPORTS     = 'fra.rep';
	public const ASPECT__BOT_REPORTS       = 'bot.rep';
	public const ASPECT__PROXY_DETECTED    = 'prx.det';
	public const ASPECT__AGENT_STRINGS     = 'agn.str';
	public const ASPECT__IPS_SIGNUPS       = 'ip.sig';
	public const ASPECT__IPS_FORWARDED     = 'for.for';

	public const ENTITY_CLASS__IP          = 'I';
	public const ENTITY_CLASS__EMAIL       = 'E';
	public const ENTITY_CLASS__DOMAIN_NAME = 'D';
	public const ENTITY_CLASS__AFP_ACCOUNT = 'A';
	public const ENTITY_CLASS__AFFILIATE   = 'F';
	public const ENTITY_CLASS__APP_WEBSITE = 'W';

	public static $mapEntityClass2Label = [
        self::ENTITY_CLASS__IP          => 'IP',
        self::ENTITY_CLASS__EMAIL       => 'email',
        self::ENTITY_CLASS__DOMAIN_NAME => 'domain name',
        self::ENTITY_CLASS__AFP_ACCOUNT => 'AFP account',
        self::ENTITY_CLASS__AFFILIATE   => 'affiliate',
        self::ENTITY_CLASS__APP_WEBSITE => 'app website',
    ];

    public static $mapAspect2Label = [
        self::ASPECT__ACTIVE_ACCOUNTS   => 'active accounts',
        self::ASPECT__PAYING_ACCOUNTS   => 'paying accounts',
        self::ASPECT__USER_ACTIONS      => 'user actions',
        self::ASPECT__SESSIONS_START    => 'sessions start',
        self::ASPECT__SIGNUP_ATTEMPT    => 'signup attempt',
        self::ASPECT__SIGNUP_SUCCESSFUL => 'signup successful',
        self::ASPECT__SIGNUP_BLOCKED    => 'signup blocked',
        self::ASPECT__ACCOUNT_UPGRADES  => 'account upgrades',
        self::ASPECT__PAYMENT_ATTEMPT   => 'payment attempt',
        self::ASPECT__PAYMENT_FAIL      => 'payment fail',
        self::ASPECT__PAYMENT_SUCCESS   => 'payment success',
        self::ASPECT__EMAIL_OPENS       => 'email opens',
        self::ASPECT__EMAIL_BOUNCES     => 'email bounces',
        self::ASPECT__DEVICES           => 'devices',
        self::ASPECT__FRAUD_REPORTS     => 'fraud reports',
        self::ASPECT__BOT_REPORTS       => 'bot reports',
        self::ASPECT__PROXY_DETECTED    => 'proxy detected',
        self::ASPECT__AGENT_STRINGS     => 'agent strings',
        self::ASPECT__IPS_SIGNUPS       => 'ips signups',
        self::ASPECT__IPS_FORWARDED     => 'forwarded for',
    ];

    /**
     * @var array
     * aspectId => 'H'|'U'|'B'  // H - HitsOnly | U - UniquesOnly | B - Both
     */
    public static $aspect2graphModeMap = [
        Aspectator::ASPECT__ACTIVE_ACCOUNTS   => 'U',
        //        Aspectator::ASPECT__PAYING_ACCOUNTS   => '',
        //        Aspectator::ASPECT__USER_ACTIONS      => '',
        //        Aspectator::ASPECT__SESSIONS_START    => '',
        Aspectator::ASPECT__SIGNUP_ATTEMPT    => 'H',
        //        Aspectator::ASPECT__SIGNUP_SUCCESSFUL => '',
        //        Aspectator::ASPECT__SIGNUP_BLOCKED    => '',
        //        Aspectator::ASPECT__ACCOUNT_UPGRADES  => '',
        Aspectator::ASPECT__PAYMENT_ATTEMPT   => 'U',
        Aspectator::ASPECT__PAYMENT_FAIL      => 'U',
        Aspectator::ASPECT__PAYMENT_SUCCESS   => 'U',
        //        Aspectator::ASPECT__EMAIL_OPENS       => '',
        //        Aspectator::ASPECT__EMAIL_BOUNCES     => '',
        //        Aspectator::ASPECT__DEVICES           => '',
        Aspectator::ASPECT__FRAUD_REPORTS     => 'U',
        //        Aspectator::ASPECT__BOT_REPORTS       => '',
        //        Aspectator::ASPECT__PROXY_DETECTED    => '',
        //        Aspectator::ASPECT__AGENT_STRINGS     => '',
        //        Aspectator::ASPECT__IPS_SIGNUPS       => '',
    ];

	public static function getDayStats( \DateInterval $interval, string $idEntity, string $idAspect)
	{
		$dStats = [
			'_id' => '',
			
		];
	}

	public static function logEvent( string $classEntity, string $idEntity, string $idAspect, ?string $idEvent=null ): void
	{
		Queue::sendData(self::$QUEUE_NAME, [ 'C' => $classEntity, 'E' => $idEntity, 'A' => $idAspect, 'U' => $idEvent ]);
	}
	
}