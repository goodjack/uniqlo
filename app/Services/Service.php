<?php

namespace App\Services;

abstract class Service
{
    /**
     * @var \App\Repositories\Repository
     */
    protected $repository;

    /**
     * @return \Illuminate\Database\Eloquent\Collection|static[]
     */
    public function all()
    {
        return $this->repository->all();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Model
     */
    public function create($attributes)
    {
        return $this->repository->create($attributes);
    }

    /**
     * @return mixed
     */
    public function first()
    {
        return $this->repository->first();
    }

    /**
     * @return mixed
     */
    public function firstBy($column, $value)
    {
        return $this->repository->firstBy($column, $value);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection|\Illuminate\Database\Eloquent\Model
     */
    public function find($id)
    {
        return $this->repository->find($id);
    }

    /**
     * @return mixed
     */
    public function findBy($column, $value)
    {
        return $this->repository->findBy($column, $value);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection|static[]
     */
    public function get()
    {
        return $this->repository->get();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection|static[]
     */
    public function getBy($column, $value)
    {
        return $this->repository->getBy($column, $value);
    }

    /**
     * @return bool|int
     */
    public function update($id, $attributes)
    {
        return $this->repository->update($id, $attributes);
    }

    /**
     * @return bool
     */
    public function updateBy($column, $value, $attributes)
    {
        return $this->repository->updateBy($column, $value, $attributes);
    }

    /**
     * @return bool|int|null
     */
    public function destroy($id)
    {
        return $this->repository->destroy($id);
    }

    /**
     * @return bool|null
     */
    public function destroyBy($column, $value)
    {
        return $this->repository->destroyBy($column, $value);
    }

    /**
     * @param string $column
     * @param int $page
     *
     * @return mixed
     *
     * @internal param $id
     */
    public function paginateBy($column, $value, $page = 12)
    {
        return $this->repository->paginateBy($column, $value, $page);
    }

    /**
     * @param int $page
     *
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function paginate($page = 12)
    {
        return $this->repository->paginate($page);
    }
}
