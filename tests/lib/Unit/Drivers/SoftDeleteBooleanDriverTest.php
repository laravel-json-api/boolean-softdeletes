<?php
/*
 * Copyright 2023 Cloud Creativity Limited
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

namespace LaravelJsonApi\BooleanSoftDeletes\Tests\Unit\Drivers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use LaravelJsonApi\BooleanSoftDeletes\Drivers\SoftDeleteBooleanDriver;
use PHPUnit\Framework\TestCase;

class SoftDeleteBooleanDriverTest extends TestCase
{
    /**
     * @return void
     */
    public function testModelMustHaveBooleanSoftDeletesTrait(): void
    {
        $model = new class extends Model {
            use SoftDeletes;
        };

        $this->expectException(\AssertionError::class);
        $this->expectExceptionMessage('Expecting a model that is boolean soft-deletable.');

        new SoftDeleteBooleanDriver($model);
    }
}
