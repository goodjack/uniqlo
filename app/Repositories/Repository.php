<?php

namespace App\Repositories;

abstract class Repository
{
    /**
     * @var \Eloquent|\DB
     */
    protected $model;

    /**
     * @param array $columns
     *
     * @return \Illuminate\Database\Eloquent\Collection|static[]
     */
    public function all($columns = ['*'])
    {
        return $this->model->all($columns);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Model
     */
    public function create($attributes)
    {
        return $this->model->create($attributes);
    }

    /**
     * @return bool
     */
    public function update($id, array $attributes, array $options = [])
    {
        $instance = $this->model->findOrFail($id);

        return $instance->update($attributes, $options);
    }

    /**
     * @return bool
     */
    public function updateBy($column, $value, array $attributes = [], array $options = [])
    {
        return $this->model->where($column, $value)->update($attributes, $options);
    }

    /**
     * @param array $columns
     *
     * @return mixed
     */
    public function first($columns = ['*'])
    {
        return $this->model->first($columns);
    }

    /**
     * @param array $columns
     *
     * @return mixed
     */
    public function firstBy($column, $value, $columns = ['*'])
    {
        return $this->model->where($column, $value)->first($columns);
    }

    /**
     * @param array $columns
     *
     * @return \Illuminate\Database\Eloquent\Collection|\Illuminate\Database\Eloquent\Model
     */
    public function find($id, $columns = ['*'])
    {
        return $this->model->find($id, $columns);
    }

    /**
     * @param array $columns
     *
     * @return mixed
     */
    public function findBy($column, $value, $columns = ['*'])
    {
        return $this->model->where($column, $value)->first($columns);
    }

    /**
     * @param array $columns
     *
     * @return \Illuminate\Database\Eloquent\Collection|static[]
     */
    public function get($columns = ['*'])
    {
        return $this->model->get($columns);
    }

    /**
     * @param array $columns
     *
     * @return \Illuminate\Database\Eloquent\Collection|static[]
     */
    public function getBy($column, $value, $columns = ['*'])
    {
        return $this->model->where($column, $value)->get($columns);
    }

    /**
     * @return int
     *
     * @internal param $id
     */
    public function destroy($ids)
    {
        return $this->model->destroy($ids);
    }

    /**
     * @return bool|null
     */
    public function destroyBy($column, $value)
    {
        return $this->model->where($column, $value)->delete();
    }

    /**
     * @param null $perPage
     * @param array $columns
     * @param string $pageName
     * @param int $page
     *
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function paginate($perPage = null, $columns = ['*'], $pageName = 'page', $page = null)
    {
        return $this->model->paginate($perPage, $columns, $pageName, $page);
    }

    /**
     * @param null $perPage
     * @param array $columns
     * @param string $pageName
     * @param int $page
     *
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function paginateBy($column, $value, $perPage = null, $columns = ['*'], $pageName = 'page', $page = null)
    {
        return $this->model->where($column, $value)->paginate($perPage, $columns, $pageName, $page);
    }
}
