<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class BufferClient
{
    public function __construct(private readonly string $token)
    {
    }

    /**
     * Look up the caller's connected channels, keyed by service (e.g. "twitter", "facebook").
     *
     * @return array<string, array{id: string, name: string}>
     */
    public function findChannels(): array
    {
        $accountQuery = <<<'GQL'
        query {
          account {
            organizations { id }
          }
        }
        GQL;

        $accountResponse = $this->request($accountQuery);
        $organizationId = data_get($accountResponse->json(), 'data.account.organizations.0.id');

        if ($accountResponse->failed() || ! $organizationId) {
            return [];
        }

        $channelsQuery = <<<'GQL'
        query Channels($organizationId: OrganizationId!) {
          channels(input: { organizationId: $organizationId }) {
            id
            name
            service
          }
        }
        GQL;

        $channelsResponse = $this->request($channelsQuery, ['organizationId' => $organizationId]);
        $channels = data_get($channelsResponse->json(), 'data.channels', []);

        $bySer = [];
        foreach ($channels as $channel) {
            $service = $channel['service'] ?? null;
            if (in_array($service, ['twitter', 'facebook'], true)) {
                $bySer[$service] = ['id' => $channel['id'], 'name' => $channel['name']];
            }
        }

        return $bySer;
    }

    /**
     * Create (or schedule) a post, optionally as an X thread.
     *
     * @param  array{text: string, channelId: string, assets: array, mode: string, dueAt: ?string, metadata: ?array}  $params
     * @return array{success: bool, error: ?string}
     */
    public function createPost(array $params): array
    {
        $mutation = <<<'GQL'
        mutation CreatePost($text: String!, $channelId: ChannelId!, $assets: [AssetInput!]!, $mode: ShareMode!, $dueAt: DateTime, $metadata: PostInputMetaData) {
          createPost(input: {
            text: $text
            channelId: $channelId
            mode: $mode
            schedulingType: automatic
            needsApproval: false
            assets: $assets
            dueAt: $dueAt
            metadata: $metadata
          }) {
            ... on PostActionSuccess {
              post { id status }
            }
            ... on MutationError {
              message
            }
          }
        }
        GQL;

        $response = $this->request($mutation, $params);

        $errorMessage = $response->json('errors.0.message')
            ?? data_get($response->json(), 'data.createPost.message');

        if ($response->failed() || $errorMessage) {
            return ['success' => false, 'error' => $errorMessage ?? $response->body()];
        }

        return ['success' => true, 'error' => null];
    }

    private function request(string $query, array $variables = [])
    {
        return Http::withToken($this->token)
            ->post('https://api.buffer.com', [
                'query' => $query,
                'variables' => (object) $variables,
            ]);
    }
}
