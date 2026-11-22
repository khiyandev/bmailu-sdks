<?php

declare(strict_types=1);

namespace Bmailu\Resources;

final class Topics extends Resource
{
    /** List topics. GET /topics */
    public function list(): mixed
    {
        return $this->client->request('GET', "/topics", null);
    }

    /** Create a topic. POST /topics */
    public function create(array $params): mixed
    {
        return $this->client->request('POST', "/topics", $this->obj($params));
    }

    /** Delete a topic. DELETE /topics/{topic_id} */
    public function delete(string|int $topicId): mixed
    {
        return $this->client->request('DELETE', "/topics/{$this->seg($topicId)}", null);
    }
}
