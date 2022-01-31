<?php /** @noinspection PhpPureAttributeCanBeAddedInspection */

/** @noinspection PhpUnused */

/** @noinspection PhpMissingReturnTypeInspection */ /** @noinspection PhpMissingFieldTypeInspection */

namespace Bbt;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Throwable;

abstract class ApiServer
{
    /**
     * @var LoggerInterface or null if no logging required
     */
    public $logger;

    /**
     * @var LoggerInterface
     */
    public $loggerErrors;

    public $fnameLog;
    public $fnameErrorLog;

    public static $arStripRequestHeadersForLog = [
        'Accept-Language', 'Accept-Encoding', 'Accept', 'Cache-Control', 'Connection', 'Content-Length', 'Host',
        'Upgrade-Insecure-Requests', 'Cookie', 'Content-Type', 'User-Agent'
    ];

    public function __construct()
    {
        $this->init();
    }

    /**
     * $rrt->response must be set in this method
     *
     * @param RequestResponseTrace $rrt
     */
    abstract protected function dispatch(RequestResponseTrace $rrt): void;

    /**
     * object initialization (called from constructor)
     */
    abstract protected function init(): void;

    /**
     * @param RequestResponseTrace $rrt
     * @return bool false if last request served. Must return false on second time call for CGI setup
     */
    abstract public function hydrateNextServerRequest(RequestResponseTrace $rrt): bool;


    /**
     * put authentication here, throw exception if needed
     */
    protected function beforeDispatch(RequestResponseTrace $rrt): void
    {
        $this->authorizeFromBearer($rrt);
    }

    final public function serve(?float $tsRequestStart=null): void
    {
        $rrt = new RequestResponseTrace();

        try {

            while ($this->hydrateNextServerRequest($rrt)) {

                $rrt->counter++;

                if (null === $tsRequestStart) $tsRequestStart = microtime(true);

                $rrt->tsStart = $tsRequestStart;

                $this->beforeDispatch($rrt);
                $this->setDataObject($rrt);
                $this->dispatch($rrt);
                $this->emitResponse($rrt);

                $rrt->duration = (int)(1000 * (microtime(true) - $rrt->tsStart));

                if (null !== $this->logger) {
                    $this->logRequestResponse($this->logger, 'debug', $rrt->rawRequest, $rrt);
                }

                $rrt->cleanup();

                $tsRequestStart = null;
            }

        } catch (Throwable $e) {

            $this->emitErrorResponse($e, $rrt);

            $rrt->duration = (int)(1000 * (microtime(true) - $rrt->tsStart));

            if( null !== $this->loggerErrors ) {
                $this->logRequestResponse($this->loggerErrors, 'error', $e->getMessage(), $rrt);
            }

            $rrt->cleanup();
        }
    }

    protected function obj2JsonResponse($responseObject): ResponseInterface
    {
        $resp = new DummyJsonResponse(200);
        $resp->hydrateFromObject($responseObject);

        return $resp;
    }

    /**
     * Set $rrt->requestDataObject of the class you need here, depend on request / target
     * @param RequestResponseTrace $rrt
     */
    protected function setDataObject(RequestResponseTrace $rrt):void
    {
        $rrt->request->getBody()->rewind();
        $rrt->rawRequest = $rrt->request->getBody()->getContents();

        if (empty($rrt->rawRequest)) return;

        $rrt->requestDataObject = @json_decode($rrt->rawRequest, false, 128);
        if( null === $rrt->requestDataObject ) {
            throw new RuntimeException('request JSON cannot be decoded');
        }
    }

    protected function emitErrorResponse($e, RequestResponseTrace $rrt):void
    {
        $err = new class(){};

        $err->error = $e->getMessage();

        $r = new DummyJsonResponse(500);
        try {
            $r->hydrateFromObject($err);
        } /** @noinspection PhpUnusedLocalVariableInspection */ catch (Throwable $e) {
            die('FATAL:cannot hydrate error');
        }

        $rrt->response = $r;

        $this->emitResponse($rrt);
    }

    protected function emitResponse(RequestResponseTrace  $rrt):void
    {
        $content = $rrt->response->getBody()->getContents();
        $size = strlen($content);

        header('Content-Length: '.$size);
        header('Cache-Control: no-cache,must-revalidate');

        foreach ($rrt->response->getHeaders() as $name => $values) {
            foreach ($values as $value) {
                header(sprintf('%s: %s', $name, $value), false);
            }
        }

        echo($content);

        $rrt->rawResponse = $content;
    }

    private function logRequestResponse(LoggerInterface $logger, string $level, string $logMessage, RequestResponseTrace $rrt):void
    {
        if (isset($rrt->request)) {
            $logContext['method']  = $rrt->request->getMethod();
            $logContext['target']  = $rrt->request->getRequestTarget();

            $arLogHeaders = [];

            foreach ($rrt->request->getHeaders() as $name => $values) {
                if( in_array($name, self::$arStripRequestHeadersForLog) ) continue;
                $arLogHeaders[] = $name . ' : ' . implode(" ", $values);
            }

            if( !empty($arLogHeaders)) {
                $logContext['headers'] = implode("\n", $arLogHeaders);
            }
        }

        if( !empty($rrt->traceLog) ) {
            $logContext['trace'] = $rrt->traceLog;
        }

        $logContext['duration'] = $rrt->duration;

        if (!empty($rrt->rawResponse)) {
            $logContext['response'] = $rrt->rawResponse;
        } else {
            $logContext['response'] = 'NO RESPONSE';
        }

        if( isset($rrt->response) && !empty($rrt->logContext) ) {
            $logContext = array_merge($logContext, $rrt->logContext);
        }

        $logContext['REMOTE_ADDR'] = @$_SERVER['REMOTE_ADDR'];

        $logger->log( $level, $logMessage, $logContext);
    }

    private function authorizeFromBearer(RequestResponseTrace $rrt): void
    {
        $authToken = $rrt->request->getHeader('Authorization');

        if (is_array($authToken)) {
            $authToken = reset($authToken);
            $authToken = substr($authToken, 7);
            $rrt->authToken = $authToken;
        }
    }
}

class RequestResponseTrace
{
    public $context;

    /**
     * @var ServerRequestInterface
     */
    public $request;
    /**
     * @var ResponseInterface
     */
    public $response;
    /**
     * @var object
     */
    public $requestDataObject;
    /**
     * @var string
     */
    public $rawResponse;
    /**
     * @var string
     */
    public $rawRequest;
    /**
     * @var string
     */
    public $traceLog;
    /**
     * @var float
     */
    public $tsStart;
    /**
     * @var int
     */
    public $duration;

    public $authToken;

    public $counter = 0;

    public $logContext = [];

    public function cleanup():void
    {
        unset(
            $this->context,
            $this->request, $this->response, $this->requestDataObject,
            $this->rawResponse, $this->rawRequest, $this->traceLog, $this->tsStart, $this->duration, $this->authToken
        );

        $this->logContext = [];
    }
}

class DummyJsonResponse implements ResponseInterface
{
    protected $statusCode;
    protected $jsonBody;
    protected $arHeaders = [
        'Content-type' => ['application/json'],
    ];

    public function __construct($statusCode) { $this->statusCode = $statusCode; }

    public function hydrateFromObject($responseObject):void
    {
        $this->jsonBody = json_encode($responseObject, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    }

    public function getBody() {return new DummyStreamBody($this->jsonBody);}
    public function getHeaders(){return $this->arHeaders;}
    public function getHeader($name){return $this->arHeaders[$name]??[];}

    public function getProtocolVersion(){}
    public function getHeaderLine($name){}
    public function getStatusCode(){return $this->statusCode;}
    public function getReasonPhrase(){}
    public function withProtocolVersion($version) {}
    public function hasHeader($name){}
    public function withHeader($name, $value){

        $new = new self($this->statusCode);
        $new->jsonBody = $this->jsonBody;
        $new->arHeaders = $this->arHeaders;
        $new->arHeaders[$name] = $value;

        return $new;
    }
    public function withAddedHeader($name, $value){}
    public function withoutHeader($name){}
    public function withBody(StreamInterface $body){}
    public function withStatus($code, $reasonPhrase = ''){}
}

class DummyTextResponse implements ResponseInterface
{
    protected $statusCode;
    protected $stringBody;
    protected $arHeaders = [
        'Content-type' => ['text/pain'],
    ];

    public function __construct($statusCode) { $this->statusCode = $statusCode; }

    public function hydrateFromString($string):void
    {
        $this->stringBody = $string;
    }

    public function getBody() {return new DummyStreamBody($this->stringBody);}
    public function getHeaders(){return $this->arHeaders;}
    public function getHeader($name){return $this->arHeaders[$name]??[];}

    public function getProtocolVersion(){}
    public function getHeaderLine($name){}
    public function getStatusCode(){return $this->statusCode;}
    public function getReasonPhrase(){}
    public function withProtocolVersion($version) {}
    public function hasHeader($name){}
    public function withHeader($name, $value){

        $new = new self($this->statusCode);
        $new->stringBody = $this->stringBody;
        $new->arHeaders = $this->arHeaders;
        $new->arHeaders[$name] = $value;

        return $new;
    }
    public function withAddedHeader($name, $value){}
    public function withoutHeader($name){}
    public function withBody(StreamInterface $body){}
    public function withStatus($code, $reasonPhrase = ''){}
}

class DummyFileResponse implements ResponseInterface
{
    protected $statusCode;
    protected $arHeaders = [
        'Content-type' => ['application/octet-stream'],
        'Content-Transfer-Encoding' => ['binary'],
    ];
    protected $fname;
    protected $fnameAttachment;

    public function __construct($statusCode) { $this->statusCode = $statusCode; }

    public function hydrateFromFileName($fname, ?string $fnameAttachment=null):void
    {
        $this->fname = $fname;
        $this->fnameAttachment = $fnameAttachment ?? basename($fname);

        $this->arHeaders['Content-disposition'] = ['attachmen; filename="' . $this->fnameAttachment . '"'];
        $this->arHeaders['Content-Length'] = [(string)filesize($this->fname)];
    }

    public function getBody() {return new DummyStreamBody(file_get_contents($this->fname));}
    public function getHeaders(){return $this->arHeaders;}
    public function getHeader($name){return $this->arHeaders[$name]??[];}

    public function getProtocolVersion(){}
    public function getHeaderLine($name){}
    public function getStatusCode(){return $this->statusCode;}
    public function getReasonPhrase(){}
    public function withProtocolVersion($version) {}
    public function hasHeader($name){}
    public function withHeader($name, $value){

        $new = new self($this->statusCode);
        $new->fname = $this->fname;
        $new->arHeaders = $this->arHeaders;
        $new->arHeaders[$name] = $value;

        return $new;
    }
    public function withAddedHeader($name, $value){}
    public function withoutHeader($name){}
    public function withBody(StreamInterface $body){}
    public function withStatus($code, $reasonPhrase = ''){}
}

class DummyStreamBody implements StreamInterface
{
    protected $body;

    public function __construct(string $body){$this->body = $body;}
    public function getContents(){return $this->body;}
    public function getSize(){return strlen($this->body);}
    public function __toString(){return $this->body;}

    public function close(){}
    public function detach(){}
    public function tell(){}
    public function eof(){}
    public function isSeekable(){}
    public function seek($offset, $whence = SEEK_SET){}
    public function rewind(){}
    public function isWritable(){}
    public function write($string){}
    public function isReadable(){}
    public function read($length){}
    public function getMetadata($key = null){}
}