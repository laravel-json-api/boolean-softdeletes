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

namespace LaravelJsonApi\BooleanSoftDeletes\Drivers;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use LaravelJsonApi\Eloquent\Drivers\StandardDriver;
use Webkid\LaravelBooleanSoftdeletes\SoftDeletesBoolean;
use function boolval;

class SoftDeleteBooleanDriver extends StandardDriver
{
    /**
     * SoftDeleteDriver constructor.
     *
     * @param Model&SoftDeletesBoolean $model
     */
    public function __construct($model)
    {
        assert(
            in_array(SoftDeletesBoolean::class, class_uses_recursive($model), true),
            'Expecting a model that is boolean soft-deletable.',
        );

        parent::__construct($model);
    }

    /**
     * @inheritDoc
     */
    public function query(): Builder
    {
        /**
         * When querying specific resources, we use `withTrashed` as we want trashed
         * resources to exist in our API.
         */
        return parent::query()->withTrashed();
    }

    /**
     * @inheritDoc
     */
    public function persist(Model $model): bool
    {
        /**
         * If the model is being restored, the restore method executes a
         * save on the model. So we only need to run the restore method and all
         * dirty attributes will be saved.
         */
        if ($this->willRestore($model)) {
            return $this->restore($model);
        }

        /**
         * To ensure Laravel still executes its soft-delete logic (e.g. firing events)
         * we need to delete before a save when we are soft-deleting. Although this
         * may result in two database calls in this scenario, it means we can guarantee
         * that standard Laravel soft-delete logic is executed.
         */
        if ($this->willSoftDelete($model)) {
            $model->delete();
        }

        return (bool) $model->save();
    }

    /**
     * @inheritDoc
     */
    public function destroy(Model $model): bool
    {
        return (bool) $model->forceDelete();
    }

    /**
     * @param Model&SoftDeletesBoolean $model
     * @return bool
     */
    private function restore(Model $model): bool
    {
        return (bool) $model->restore();
    }

    /**
     * Will the hydration operation restore the model?
     *
     * @param Model&SoftDeletesBoolean $model
     * @return bool
     */
    private function willRestore(Model $model): bool
    {
        if (!$model->exists) {
            return false;
        }

        $column = $model->getIsDeletedColumn();

        return false !== boolval($model->getOriginal($column)) && false === boolval($model->{$column});
    }

    /**
     * Will the hydration operation result in the model being soft deleted?
     *
     * @param Model&SoftDeletesBoolean $model
     * @return bool
     */
    private function willSoftDelete(Model $model): bool
    {
        if (!$model->exists) {
            return false;
        }

        $column = $model->getIsDeletedColumn();

        return false === boolval($model->getOriginal($column)) && false !== boolval($model->{$column});
    }

}
