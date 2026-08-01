<?php

namespace Tests\Unit;

use App\Services\Odoo\OdooClient;
use App\Services\Odoo\OdooException;
use GuzzleHttp\Exception\RequestException as GuzzleRequestException;
use GuzzleHttp\Psr7\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OdooClientTest extends TestCase
{
    public function test_it_wraps_transport_level_guzzle_request_exceptions_as_odoo_exceptions(): void
    {
        Http::fake(function () {
            throw new GuzzleRequestException(
                'cURL error 60: SSL certificate problem: unable to get local issuer certificate',
                new Request('POST', 'https://example.com/xmlrpc/2/common')
            );
        });

        $client = new OdooClient('https://example.com', 'odoo_db', 10);

        try {
            $client->authenticate('employee@example.com', '1234');
            $this->fail('Expected the Odoo client to wrap the transport exception.');
        } catch (OdooException $exception) {
            $this->assertSame('Unable to reach Odoo right now. Please try again shortly.', $exception->getMessage());
            $this->assertInstanceOf(GuzzleRequestException::class, $exception->getPrevious());
        }
    }
}
