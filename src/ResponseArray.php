<?php
namespace Diza\TGScraper;

class ResponseArray extends \ArrayObject
{
    private $headers;

    const JOBS_PER_PAGE = 50;

    /**
     * @param array $headers
     * @param array $body
     */
    public function __construct($headers, $body)
    {
        $this->headers = $headers;
        parent::__construct($body);
    }
}
