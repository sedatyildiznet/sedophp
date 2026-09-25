<?php

declare(strict_types=1);

namespace SedoPHP\Http;

use SedoPHP\Validation\Validator;

abstract class FormRequest
{
    /** @var array<string,list<string>>|null */
    private ?array $validationErrors = null;

    public function __construct(private readonly Request $request)
    {
    }

    /** @return array<string,string|array<int,string>> */
    abstract public function rules(): array;

    /** @return array<string,list<string>> */
    public function errors(): array
    {
        if ($this->validationErrors === null) {
            $this->validationErrors = Validator::validate($this->request->all(), $this->rules());
        }

        return $this->validationErrors;
    }

    public function passes(): bool
    {
        return $this->errors() === [];
    }

    public function fails(): bool
    {
        return !$this->passes();
    }

    /** @return array<string,mixed> */
    public function validated(): array
    {
        if ($this->fails()) {
            throw new HttpException(422, 'Validation failed.');
        }

        $input = $this->request->all();
        $result = [];

        foreach (array_keys($this->rules()) as $path) {
            if (str_contains($path, '*') || str_contains($path, '.')) {
                return $input;
            }

            if (array_key_exists($path, $input)) {
                $result[$path] = $input[$path];
            }
        }

        return $result;
    }

    public function input(?string $key = null, mixed $default = null): mixed
    {
        return $this->request->input($key, $default);
    }

    public function request(): Request
    {
        return $this->request;
    }
}
