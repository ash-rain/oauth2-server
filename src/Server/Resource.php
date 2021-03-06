<?php namespace Dingo\OAuth2\Server;

use Dingo\OAuth2\Storage\Adapter;
use Dingo\OAuth2\Entity\Token as TokenEntity;
use Symfony\Component\HttpFoundation\Request;
use Dingo\OAuth2\Exception\InvalidTokenException;

class Resource {

	/**
	 * Storage adapter instance.
	 * 
	 * @var \Dingo\OAuth2\Storage\Adapter
	 */
	protected $storage;

	/**
	 * Symfony request instance.
	 * 
	 * @var \Symfony\Component\HttpFoundation\Request
	 */
	protected $request;

	/**
	 * Array of default scopes.
	 * 
	 * @var array
	 */
	protected $defaultScopes = [];

	/**
	 * Authenticated access token.
	 * 
	 * @var \Dingo\OAuth2\Entity\Token
	 */
	protected $token;

	/**
	 * Create a new Dingo\OAuth2\Server\Resource instance.
	 * 
	 * @param  \Dingo\OAuth2\Storage\Adapter  $storage
	 * @param  \Symfony\Component\HttpFoundation\Request  $request
	 * @return void
	 */
	public function __construct(Adapter $storage, Request $request = null)
	{
		$this->storage = $storage;
		$this->request = $request ?: Request::createFromGlobals();
	}

	/**
	 * Validate an access token.
	 * 
	 * @param  string|array  $scopes
	 * @return \Dingo\OAuth2\Entity\Token
	 * @throws \Dingo\OAuth2\Exception\InvalidTokenException
	 */
	public function validateRequest($scopes = null)
	{
		if ( ! $token = $this->findAccessToken())
		{
			throw new InvalidTokenException('missing_parameter', 'Access token was not supplied.', 401);
		}

		if ( ! $token = $this->storage('token')->getWithScopes($token))
		{
			throw new InvalidTokenException('unknown_token', 'Invalid access token.', 401);
		}

		if ($this->tokenHasExpired($token))
		{
			$this->storage('token')->delete($token->getToken());

			throw new InvalidTokenException('expired_token', 'Access token has expired.', 401);
		}

		$this->validateTokenScopes($token, $scopes);

		return $this->token = $token;
	}

	/**
	 * Determine if a token has expired.
	 * 
	 * @param  \Dingo\OAuth2\Entity\Token  $token
	 * @return bool
	 */
	protected function tokenHasExpired(TokenEntity $token)
	{
		return $token->getExpires() < time();
	}

	/**
	 * Validate token scopes.
	 * 
	 * @param  \Dingo\OAuth2\Entity\Token  $token
	 * @param  string|array  $scopes
	 * @return void
	 * @throws \Dingo\OAuth2\Exception\InvalidTokenException
	 */
	protected function validateTokenScopes(TokenEntity $token, $scopes)
	{
		// Build our array of scopes by merging the provided scopes with the
		// default scopes that are used for every request.
		$scopes = array_merge($this->defaultScopes, (array) $scopes);

		foreach ($scopes as $scope)
		{
			if ( ! $token->hasScope($scope))
			{
				throw new InvalidTokenException('mismatched_scope', 'Requested scope "'.$scope.'" is not associated with this access token.', 401);
			}
		}
	}

	/**
	 * Find the access token in either the header or request body.
	 * 
	 * @return bool|string
	 */
	public function findAccessToken()
	{
		if ($header = $this->request->headers->get('authorization'))
		{
			if (preg_match('/Bearer (\S+)/', $header, $matches))
			{
				list($header, $token) = $matches;

				return $token;
			}
		}
		elseif ($this->request->get('access_token'))
		{
			return $this->request->get('access_token');
		}

		return false;
	}

	/**
	 * Get the authenticated access token.
	 * 
	 * @return \Dingo\OAuth2\Entity\Token
	 */
	public function getToken()
	{
		return $this->token;
	}

	/**
	 * Set the default scopes.
	 * 
	 * @param  array  $scopes
	 * @return \Dingo\OAuth2\Server\Resource
	 */
	public function setDefaultScopes(array $scopes)
	{
		$this->defaultScopes = $scopes;

		return $this;
	}

	/**
	 * Get a specific storage from the storage adapter.
	 * 
	 * @param  string  $storage
	 * @return mixed
	 */
	public function storage($storage)
	{
		return $this->storage->get($storage);
	}

}