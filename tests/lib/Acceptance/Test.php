<?php
/*
 * Copyright 2025 Cloud Creativity Limited
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

declare(strict_types=1);

namespace LaravelJsonApi\BooleanSoftDeletes\Tests\Acceptance;

use App\Models\Post;
use App\Schemas\PostSchema;

class Test extends TestCase
{

    /**
     * @var PostSchema
     */
    private PostSchema $schema;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->schema = $this->app->make(PostSchema::class);
    }

    /**
     * @return array
     */
    public static function trashedProvider(): array
    {
        return [
            'trashed' => [true],
            'not_trashed' => [false],
        ];
    }

    /**
     * @param $deleted
     * @dataProvider trashedProvider
     */
    public function testFind($deleted): void
    {
        $post = Post::factory()->create(['is_deleted' => $deleted]);

        $actual = $this->schema
            ->repository()
            ->find((string) $post->getRouteKey());

        $this->assertTrue($post->is($actual));
    }

    /**
     * @param $deleted
     * @dataProvider trashedProvider
     */
    public function testExists($deleted): void
    {
        $post = Post::factory()->create(['is_deleted' => $deleted]);

        $actual = $this->schema
            ->repository()
            ->exists((string) $post->getRouteKey());

        $this->assertTrue($actual);
    }

    public function testFindMany(): void
    {
        $posts = Post::factory()->count(3)->sequence(
            ['is_deleted' => false],
            ['is_deleted' => true],
        )->create();

        Post::factory()->create(['is_deleted' => false]);
        Post::factory()->create(['is_deleted' => true]);

        $ids = $posts
            ->map(fn(Post $post) => (string) $post->getRouteKey())
            ->all();

        $actual = $this->schema
            ->repository()
            ->findMany($ids);

        $this->assertCount(count($posts), $actual);
    }

    /**
     * @param $deleted
     * @dataProvider trashedProvider
     */
    public function testQueryOne($deleted): void
    {
        $post = Post::factory()->create(['is_deleted' => $deleted]);

        $actual = $this->schema
            ->repository()
            ->queryOne((string) $post->getRouteKey())
            ->first();

        $this->assertTrue($post->is($actual));
    }

    /**
     * @param $deleted
     * @dataProvider trashedProvider
     */
    public function testItForceDeletesModel($deleted): void
    {
        $forceDeleted = false;

        Post::forceDeleted(function () use (&$forceDeleted) {
            $forceDeleted = true;
        });

        $post = Post::factory()->create(['is_deleted' => $deleted]);

        $this->schema->repository()->delete((string) $post->getRouteKey());

        $this->assertModelMissing($post);
        $this->assertTrue($forceDeleted);
    }

    public function testItDoesNotRestoreOnCreate(): void
    {
        $post = Post::factory()->make();

        $data = [
            'content' => $post->content,
            'isDeleted' => false,
            'slug' => $post->slug,
            'title' => $post->title,
        ];

        $this->willNotRestore()
            ->willNotSoftDelete()
            ->willNotForceDelete();

        $actual = $this->schema
            ->repository()
            ->create()
            ->store($data);

        $this->assertFalse($actual->trashed());

        $this->assertDatabaseHas('posts', [
            $post->getKeyName() => $actual->getKey(),
            'content' => $post->content,
            'is_deleted' => false,
            'slug' => $post->slug,
            'title' => $post->title,
        ]);
    }

    /**
     * We cannot soft delete on create because the model `delete()` method
     * does not run if the model does not exist. This means Laravel does not
     * allow us to soft delete a model we are creating.
     *
     * If the client provides a value for the soft delete column when creating,
     * we expect the model to be created in a trashed state, but without any
     * deleting events firing (as Laravel does not allow that).
     *
     * If the developer wants to prevent a client from soft-deleting the
     * model on create, they should use validation rules to reject the
     * request: or omit the deleted column value by not validating it on a
     * create request.
     */
    public function testItDoesNotSoftDeleteOnCreate(): void
    {
        $post = Post::factory()->make();

        $data = [
            'content' => $post->content,
            'isDeleted' => true,
            'slug' => $post->slug,
            'title' => $post->title,
        ];

        $this->willNotRestore()
            ->willNotSoftDelete()
            ->willNotForceDelete();

        $actual = $this->schema
            ->repository()
            ->create()
            ->store($data);

        $this->assertTrue($actual->trashed());

        $this->assertDatabaseHas('posts', [
            $post->getKeyName() => $actual->getKey(),
            'content' => $post->content,
            'is_deleted' => true,
            'slug' => $post->slug,
            'title' => $post->title,
        ]);
    }

    public function testItSoftDeletesOnUpdate(): void
    {
        $deleted = false;

        Post::deleted(function () use (&$deleted) {
            $deleted = true;
        });

        $post = Post::factory()->create(['is_deleted' => false]);

        $this->willNotRestore()->willNotForceDelete();

        $actual = $this->schema
            ->repository()
            ->update($post)
            ->store(['isDeleted' => true]);

        $this->assertTrue($actual->trashed());
        $this->assertTrue($deleted);
        $this->assertBooleanSoftDeleted($post);
    }

    public function testItSoftDeletesAndUpdatesOtherFields(): void
    {
        $deleted = false;

        Post::deleted(function () use (&$deleted) {
            $deleted = true;
        });

        $post = Post::factory()->create(['is_deleted' => false]);

        $data = [
            'isDeleted' => true,
            'title' => 'Hello World!',
        ];

        $this->willNotRestore()->willNotForceDelete();

        $actual = $this->schema
            ->repository()
            ->update($post)
            ->store($data);

        $this->assertTrue($actual->trashed());
        $this->assertTrue($deleted);

        $this->assertDatabaseHas('posts', array_merge($post->getOriginal(), [
            'is_deleted' => true,
            'title' => $data['title'],
        ]));
    }

    public function testItDoesNotSoftDeleteOnUpdate(): void
    {
        $post = Post::factory()->create(['is_deleted' => false]);

        $data = ['isDeleted' => false, 'title' => 'Hello World!'];

        $this->willNotSoftDelete()
            ->willNotForceDelete()
            ->willNotRestore();

        $actual = $this->schema
            ->repository()
            ->update($post)
            ->store($data);

        $this->assertFalse($actual->trashed());

        $this->assertDatabaseHas('posts', array_merge($post->getOriginal(), [
            'is_deleted' => false,
            'title' => $data['title'],
        ]));
    }

    public function testItRestores(): void
    {
        $restored = false;

        Post::restored(function () use (&$restored) {
            $restored = true;
        });

        $post = Post::factory()->create(['is_deleted' => true]);

        $data = [
            'isDeleted' => false,
        ];

        $this->willNotSoftDelete()->willNotForceDelete();

        $actual = $this->schema
            ->repository()
            ->update($post)
            ->store($data);

        $this->assertFalse($actual->trashed());
        $this->assertTrue($restored);
        $this->assertNotBooleanSoftDeleted($post);
    }

    public function testItRestoresAndUpdatesOtherFields(): void
    {
        $restored = false;

        Post::restored(function () use (&$restored) {
            $restored = true;
        });

        $post = Post::factory()->create(['is_deleted' => true]);

        $data = [
            'isDeleted' => false,
            'title' => 'Hello World!',
        ];

        $this->willNotSoftDelete()->willNotForceDelete();

        $actual = $this->schema
            ->repository()
            ->update($post)
            ->store($data);

        $this->assertFalse($actual->trashed());
        $this->assertTrue($restored);

        $this->assertDatabaseHas('posts', array_merge($post->getOriginal(), [
            'is_deleted' => false,
            'title' => $data['title'],
        ]));
    }

    public function testItDoesNotRestoreOnUpdate(): void
    {
        $post = Post::factory()->create(['is_deleted' => true]);

        $data = ['isDeleted' => true, 'title' => 'Hello World!'];

        $this->willNotSoftDelete()
            ->willNotForceDelete()
            ->willNotRestore();

        $actual = $this->schema
            ->repository()
            ->update($post)
            ->store($data);

        $this->assertTrue($actual->trashed());

        $this->assertDatabaseHas('posts', array_merge($post->getOriginal(), [
            'is_deleted' => true,
            'title' => $data['title'],
        ]));
    }

    public function testWithTrashedIsTrue(): void
    {
        $posts = Post::factory()->count(5)->sequence(
            ['is_deleted' => false],
            ['is_deleted' => true],
        )->create();

        $actual = $this->schema
            ->repository()
            ->queryAll()
            ->filter(['withTrashed' => 'true'])
            ->get();

        $this->assertPosts($posts, $actual);
    }

    public function testWithTrashedIsFalse(): void
    {
        $posts = Post::factory()->count(5)->sequence(
            ['is_deleted' => false],
            ['is_deleted' => true],
        )->create();

        $expected = $posts->reject(fn(Post $post) => $post->trashed());

        $actual = $this->schema
            ->repository()
            ->queryAll()
            ->filter(['withTrashed' => 'false'])
            ->get();

        $this->assertPosts($expected, $actual);
    }

    public function testOnlyTrashedIsTrue(): void
    {
        $posts = Post::factory()->count(5)->sequence(
            ['is_deleted' => false],
            ['is_deleted' => true],
        )->create();

        $expected = $posts->filter(fn(Post $post) => $post->trashed());

        $actual = $this->schema
            ->repository()
            ->queryAll()
            ->filter(['trashed' => 'true'])
            ->get();

        $this->assertPosts($expected, $actual);
    }

    public function testOnlyTrashedIsFalse(): void
    {
        $posts = Post::factory()->count(5)->sequence(
            ['is_deleted' => false],
            ['is_deleted' => true],
        )->create();

        $expected = $posts->reject(fn(Post $post) => $post->trashed());

        $actual = $this->schema
            ->repository()
            ->queryAll()
            ->filter(['trashed' => 'false'])
            ->get();

        $this->assertPosts($expected, $actual);
    }

    /**
     * @param $expected
     * @param $actual
     */
    private function assertPosts($expected, $actual): void
    {
        $this->assertCount(count($expected), $actual);

        $this->assertSame(
            collect($expected)->sortBy('id')->pluck('id')->all(),
            collect($actual)->sortBy('id')->pluck('id')->all()
        );
    }

    /**
     * @param Post $post
     * @param bool $deleted
     * @return void
     */
    private function assertBooleanSoftDeleted(Post $post, bool $deleted = true): void
    {
        $this->assertDatabaseHas($post->getTable(), [
            $post->getKeyName() => $post->getKey(),
            $post->getIsDeletedColumn() => $deleted,
        ]);
    }

    /**
     * @param Post $post
     * @return void
     */
    private function assertNotBooleanSoftDeleted(Post $post): void
    {
        $this->assertBooleanSoftDeleted($post, false);
    }

    /**
     * @return $this
     */
    private function willNotSoftDelete(): self
    {
        Post::deleted(function () {
            throw new \LogicException('Not expecting a restore event.');
        });

        return $this;
    }

    /**
     * @return $this
     */
    private function willNotRestore(): self
    {
        Post::restored(function () {
            throw new \LogicException('Not expecting a restore event.');
        });

        return $this;
    }

    /**
     * @return $this
     */
    private function willNotForceDelete(): self
    {
        Post::forceDeleted(function () {
            throw new \LogicException('Not expecting a restore event.');
        });

        return $this;
    }
}
