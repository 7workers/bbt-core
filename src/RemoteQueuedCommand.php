<?php namespace Bbt;

class RemoteQueuedCommand
{
    public const STATUS_ERROR   = 'ERROR';
    public const STATUS_QUEUED  = 'QUEUED';
    public const STATUS_RUNNING = 'RUNNING';
    public const STATUS_OK      = 'OK';
    public const STATUS_FAILED  = 'FAILED';

    public static string $sshFnameIdentity;
    public static int $sshPort;
    public static string $sshHost;
    public static string $sshUser;
    public static string $remoteBqBin = '~/bin/bq';

    private string $cmd;
    private string $id;

    public function __construct($cmd)
    {
        $this->cmd = $cmd;
    }

    public function getStatus():string
    {
        $cmd = 'ssh -i '.self::$sshFnameIdentity.' -p '.self::$sshPort.' '.self::$sshUser.'@'.self::$sshHost.' \'tree /dev/shm/bq-'.self::$sshUser.'-default\' 2>/dev/null';

        $output = [];
        $code = 0;

        exec($cmd, $output, $code);


        $insideDir = false;
        $folder    = null;

        foreach ($output as $line) {
            if( $line == '/dev/shm/bq-'.self::$sshUser.'-default' ) {
                $insideDir = true;
            }
            if( empty(trim($line))) {
                $insideDir = false;
                $folder = null;
            }
            if( $insideDir ) {
                if ($line == '├── q') $folder = 'q';
                if ($line == '├── w') $folder = 'w';
                if ($line == '├── OK') $folder = 'OK';
                if (str_contains($line, $this->id)) {
                    if (str_contains($line, 'running')) return self::STATUS_RUNNING;
                    if ($folder == 'q') return self::STATUS_QUEUED;
                    if ($folder == 'OK') {
                        if (str_contains($line, 'exitcode')) {
                            $code = (int)substr($line, -1);
                            if ($code === 0) return self::STATUS_OK;
                            return self::STATUS_FAILED;
                        }
                    }
                }
            }
        }

        return self::STATUS_ERROR;
    }

    public function queue():void
    {
        $cmd = 'ssh -i '.self::$sshFnameIdentity.' -p '.self::$sshPort.' '.self::$sshUser.'@'.self::$sshHost.' \''.self::$remoteBqBin.' '.$this->cmd.'\' 2>/dev/null';

        $output = [];
        $code = 0;

        exec($cmd, $output, $code);

        $cmdId =  $output[0];
        if( preg_match('/^\d+\.\d+\..+$/', $cmdId) ) {
            $this->id = $cmdId;
            return;
        }

        /** @noinspection PhpFullyQualifiedNameUsageInspection */
        throw new \RuntimeException('WRONG RESPONSE FROM SERVER: '.implode("\n", $output));
    }

    public static function scpToLocal(string $fnameRemote, string $fnameLocal):void
    {
        $cmd = 'scp -i '.self::$sshFnameIdentity.' -q -P '.self::$sshPort.' '.self::$sshUser.'@'.self::$sshHost.':'.$fnameRemote.' '.$fnameLocal;

        $output = [];
        $code = 0;

        exec($cmd, $output, $code);
    }

    public function __toString(): string
    {
        return $this->cmd;
    }
}