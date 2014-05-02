<?php

namespace LeagueTests\Grant;

use League\OAuth2\Server\Grant\RefreshToken;
use League\OAuth2\Server\Entity\ScopeEntity;
use League\OAuth2\Server\Entity\ClientEntity;
use League\OAuth2\Server\Entity\AccessTokenEntity;
use League\OAuth2\Server\Entity\SessionEntity;
use League\OAuth2\Server\Entity\RefreshTokenEntity;
use League\OAuth2\Server\AuthorizationServer;
use Mockery as M;

class RefreshTokenTest extends \PHPUnit_Framework_TestCase
{
    function testSetRefreshTokenTTL()
    {
        $grant = new RefreshToken;
        $grant->setRefreshTokenTTL(86400);

        $property = new \ReflectionProperty($grant, 'refreshTokenTTL');
        $property->setAccessible(true);

        $this->assertEquals(86400, $property->getValue($grant));
    }

    function testCompleteFlowMissingClientId()
    {
        $this->setExpectedException('League\OAuth2\Server\Exception\InvalidRequestException');

        $_POST['grant_type'] = 'refresh_token';

        $server = new AuthorizationServer;
        $grant = new RefreshToken;

        $server->addGrantType($grant);
        $server->issueAccessToken();
    }

    function testCompleteFlowMissingClientSecret()
    {
        $this->setExpectedException('League\OAuth2\Server\Exception\InvalidRequestException');

        $_POST = [
            'grant_type' => 'refresh_token',
            'client_id'  =>  'testapp'
        ];

        $server = new AuthorizationServer;
        $grant = new RefreshToken;

        $server->addGrantType($grant);
        $server->issueAccessToken();
    }

    function testCompleteFlowInvalidClient()
    {
        $this->setExpectedException('League\OAuth2\Server\Exception\InvalidClientException');

        $_POST = [
            'grant_type' => 'refresh_token',
            'client_id' =>  'testapp',
            'client_secret' =>  'foobar'
        ];

        $server = new AuthorizationServer;
        $grant = new RefreshToken;

        $clientStorage = M::mock('League\OAuth2\Server\Storage\ClientInterface');
        $clientStorage->shouldReceive('setServer');
        $clientStorage->shouldReceive('get')->andReturn(null);

        $server->setClientStorage($clientStorage);

        $server->addGrantType($grant);
        $server->issueAccessToken();
    }

    function testCompleteFlowMissingRefreshToken()
    {
        $this->setExpectedException('League\OAuth2\Server\Exception\InvalidRequestException');

        $_POST = [
            'grant_type'    => 'refresh_token',
            'client_id'     =>  'testapp',
            'client_secret' =>  'foobar',
        ];

        $server = new AuthorizationServer;
        $grant = new RefreshToken;

        $clientStorage = M::mock('League\OAuth2\Server\Storage\ClientInterface');
        $clientStorage->shouldReceive('setServer');
        $clientStorage->shouldReceive('get')->andReturn(
            (new ClientEntity($server))->setId('testapp')
        );

        $sessionStorage = M::mock('League\OAuth2\Server\Storage\SessionInterface');
        $sessionStorage->shouldReceive('setServer');

        $scopeStorage = M::mock('League\OAuth2\Server\Storage\ScopeInterface');
        $scopeStorage->shouldReceive('setServer');

        $server->setClientStorage($clientStorage);
        $server->setScopeStorage($scopeStorage);
        $server->setSessionStorage($sessionStorage);
        $server->requireScopeParam(true);

        $server->addGrantType($grant);
        $server->issueAccessToken();
    }

    function testCompleteFlowInvalidRefreshToken()
    {
        $this->setExpectedException('League\OAuth2\Server\Exception\InvalidRefreshException');

        $_POST = [
            'grant_type'    => 'refresh_token',
            'client_id'     =>  'testapp',
            'client_secret' =>  'foobar',
            'refresh_token' =>  'meh'
        ];

        $server = new AuthorizationServer;
        $grant = new RefreshToken;

        $clientStorage = M::mock('League\OAuth2\Server\Storage\ClientInterface');
        $clientStorage->shouldReceive('setServer');
        $clientStorage->shouldReceive('get')->andReturn(
            (new ClientEntity($server))->setId('testapp')
        );

        $refreshTokenStorage = M::mock('League\OAuth2\Server\Storage\RefreshTokenInterface');
        $refreshTokenStorage->shouldReceive('get');
        $refreshTokenStorage->shouldReceive('setServer');

        $scopeStorage = M::mock('League\OAuth2\Server\Storage\ScopeInterface');
        $scopeStorage->shouldReceive('setServer');

        $server->setClientStorage($clientStorage);
        $server->setScopeStorage($scopeStorage);
        $server->setRefreshTokenStorage($refreshTokenStorage);
        $server->requireScopeParam(true);

        $server->addGrantType($grant);
        $server->issueAccessToken();
    }

    function testCompleteFlowExistingScopes()
    {
        $_POST = [
            'grant_type'    => 'refresh_token',
            'client_id'     =>  'testapp',
            'client_secret' =>  'foobar',
            'refresh_token' =>  'refresh_token'
        ];

        $server = new AuthorizationServer;
        $grant = new RefreshToken;

        $clientStorage = M::mock('League\OAuth2\Server\Storage\ClientInterface');
        $clientStorage->shouldReceive('setServer');
        $clientStorage->shouldReceive('get')->andReturn(
            (new ClientEntity($server))->setId('testapp')
        );

        $sessionStorage = M::mock('League\OAuth2\Server\Storage\SessionInterface');
        $sessionStorage->shouldReceive('setServer');
        $sessionStorage->shouldReceive('getScopes')->shouldReceive('getScopes')->andReturn([]);
        $sessionStorage->shouldReceive('associateScope');
        $sessionStorage->shouldReceive('getByAccessToken')->andReturn(
            (new SessionEntity($server))
        );

        $accessTokenStorage = M::mock('League\OAuth2\Server\Storage\AccessTokenInterface');
        $accessTokenStorage->shouldReceive('setServer');
        $accessTokenStorage->shouldReceive('getByRefreshToken')->andReturn(
            (new AccessTokenEntity($server))
        );
        $accessTokenStorage->shouldReceive('delete');
        $accessTokenStorage->shouldReceive('create');
        $accessTokenStorage->shouldReceive('getScopes')->andReturn([
            (new ScopeEntity($server))->setId('foo')
        ]);
        $accessTokenStorage->shouldReceive('associateScope');

        $refreshTokenStorage = M::mock('League\OAuth2\Server\Storage\RefreshTokenInterface');
        $refreshTokenStorage->shouldReceive('setServer');
        $refreshTokenStorage->shouldReceive('associateScope');
        $refreshTokenStorage->shouldReceive('delete');
        $refreshTokenStorage->shouldReceive('create');
        $refreshTokenStorage->shouldReceive('get')->andReturn(
            (new RefreshTokenEntity($server))
        );

        $scopeStorage = M::mock('League\OAuth2\Server\Storage\ScopeInterface');
        $scopeStorage->shouldReceive('setServer');
        $scopeStorage->shouldReceive('get')->andReturn(
            (new ScopeEntity($server))->setId('foo')
        );

        $server->setClientStorage($clientStorage);
        $server->setScopeStorage($scopeStorage);
        $server->setSessionStorage($sessionStorage);
        $server->setAccessTokenStorage($accessTokenStorage);
        $server->setRefreshTokenStorage($refreshTokenStorage);

        $server->addGrantType($grant);
        $response = $server->issueAccessToken();

        $this->assertTrue(isset($response['access_token']));
        $this->assertTrue(isset($response['refresh_token']));
        $this->assertTrue(isset($response['token_type']));
        $this->assertTrue(isset($response['expires_in']));
        $this->assertTrue(isset($response['expires']));
    }

    function testCompleteFlowRequestScopes()
    {
        $_POST = [
            'grant_type'    => 'refresh_token',
            'client_id'     =>  'testapp',
            'client_secret' =>  'foobar',
            'refresh_token' =>  'refresh_token',
            'scope'         =>  'foo'
        ];

        $server = new AuthorizationServer;
        $grant = new RefreshToken;

        $oldSession = (new SessionEntity($server))->associateScope((new ScopeEntity($server))->setId('foo'));

        $clientStorage = M::mock('League\OAuth2\Server\Storage\ClientInterface');
        $clientStorage->shouldReceive('setServer');
        $clientStorage->shouldReceive('get')->andReturn(
            (new ClientEntity($server))->setId('testapp')
        );

        $sessionStorage = M::mock('League\OAuth2\Server\Storage\SessionInterface');
        $sessionStorage->shouldReceive('setServer');
        $sessionStorage->shouldReceive('getScopes')->shouldReceive('getScopes')->andReturn([]);
        $sessionStorage->shouldReceive('associateScope');
        $sessionStorage->shouldReceive('getByAccessToken')->andReturn(
            $oldSession
        );

        $accessTokenStorage = M::mock('League\OAuth2\Server\Storage\AccessTokenInterface');
        $accessTokenStorage->shouldReceive('setServer');
        $accessTokenStorage->shouldReceive('getByRefreshToken')->andReturn(
            (new AccessTokenEntity($server))
        );
        $accessTokenStorage->shouldReceive('delete');
        $accessTokenStorage->shouldReceive('create');
        $accessTokenStorage->shouldReceive('getScopes')->andReturn([
            (new ScopeEntity($server))->setId('foo')
        ]);
        $accessTokenStorage->shouldReceive('associateScope');

        $refreshTokenStorage = M::mock('League\OAuth2\Server\Storage\RefreshTokenInterface');
        $refreshTokenStorage->shouldReceive('setServer');
        $refreshTokenStorage->shouldReceive('associateScope');
        $refreshTokenStorage->shouldReceive('delete');
        $refreshTokenStorage->shouldReceive('create');
        $refreshTokenStorage->shouldReceive('get')->andReturn(
            (new RefreshTokenEntity($server))
        );

        $scopeStorage = M::mock('League\OAuth2\Server\Storage\ScopeInterface');
        $scopeStorage->shouldReceive('setServer');
        $scopeStorage->shouldReceive('get')->andReturn(
            (new ScopeEntity($server))->setId('foo')
        );

        $server->setClientStorage($clientStorage);
        $server->setScopeStorage($scopeStorage);
        $server->setSessionStorage($sessionStorage);
        $server->setAccessTokenStorage($accessTokenStorage);
        $server->setRefreshTokenStorage($refreshTokenStorage);

        $server->addGrantType($grant);
        $response = $server->issueAccessToken();

        $this->assertTrue(isset($response['access_token']));
        $this->assertTrue(isset($response['refresh_token']));
        $this->assertTrue(isset($response['token_type']));
        $this->assertTrue(isset($response['expires_in']));
        $this->assertTrue(isset($response['expires']));
    }

    function testCompleteFlowRequestScopesInvalid()
    {
        $_POST = [
            'grant_type'    => 'refresh_token',
            'client_id'     =>  'testapp',
            'client_secret' =>  'foobar',
            'refresh_token' =>  'refresh_token',
            'scope'         =>  'blah'
        ];

        $server = new AuthorizationServer;
        $grant = new RefreshToken;

        $oldSession = (new SessionEntity($server))->associateScope((new ScopeEntity($server))->setId('foo'));

        $clientStorage = M::mock('League\OAuth2\Server\Storage\ClientInterface');
        $clientStorage->shouldReceive('setServer');
        $clientStorage->shouldReceive('get')->andReturn(
            (new ClientEntity($server))->setId('testapp')
        );

        $sessionStorage = M::mock('League\OAuth2\Server\Storage\SessionInterface');
        $sessionStorage->shouldReceive('setServer');
        $sessionStorage->shouldReceive('getScopes')->shouldReceive('getScopes')->andReturn([]);
        $sessionStorage->shouldReceive('associateScope');
        $sessionStorage->shouldReceive('getByAccessToken')->andReturn(
            $oldSession
        );

        $accessTokenStorage = M::mock('League\OAuth2\Server\Storage\AccessTokenInterface');
        $accessTokenStorage->shouldReceive('setServer');
        $accessTokenStorage->shouldReceive('getByRefreshToken')->andReturn(
            (new AccessTokenEntity($server))
        );
        $accessTokenStorage->shouldReceive('delete');
        $accessTokenStorage->shouldReceive('create');
        $accessTokenStorage->shouldReceive('getScopes')->andReturn([
            (new ScopeEntity($server))->setId('foo')
        ]);
        $accessTokenStorage->shouldReceive('associateScope');

        $refreshTokenStorage = M::mock('League\OAuth2\Server\Storage\RefreshTokenInterface');
        $refreshTokenStorage->shouldReceive('setServer');
        $refreshTokenStorage->shouldReceive('associateScope');
        $refreshTokenStorage->shouldReceive('delete');
        $refreshTokenStorage->shouldReceive('create');
        $refreshTokenStorage->shouldReceive('get')->andReturn(
            (new RefreshTokenEntity($server))
        );

        $scopeStorage = M::mock('League\OAuth2\Server\Storage\ScopeInterface');
        $scopeStorage->shouldReceive('setServer');
        $scopeStorage->shouldReceive('get')->andReturn(
            (new ScopeEntity($server))->setId('blah')
        );

        $server->setClientStorage($clientStorage);
        $server->setScopeStorage($scopeStorage);
        $server->setSessionStorage($sessionStorage);
        $server->setAccessTokenStorage($accessTokenStorage);
        $server->setRefreshTokenStorage($refreshTokenStorage);

        $server->addGrantType($grant);

        $this->setExpectedException('League\OAuth2\Server\Exception\InvalidScopeException');

        $server->issueAccessToken();
    }
}