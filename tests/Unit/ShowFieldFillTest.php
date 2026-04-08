<?php

namespace Dcat\Admin\Tests\Unit;

use Dcat\Admin\Show\Field;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Fluent;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Show\Field::fill：Eloquent 使用 data_get 解析点号路径；Fluent 等仍走 toArray + Arr::get。
 *
 * 通过 Reflection 跳过 Field 构造函数（避免依赖 admin 容器），仅覆盖 fill 行为。
 */
class ShowFieldFillTest extends TestCase
{
    /**
     * @return Field
     */
    protected function makeFieldWithoutConstruct(string $name)
    {
        $ref = new ReflectionClass(Field::class);
        /** @var Field $field */
        $field = $ref->newInstanceWithoutConstructor();
        $nameProp = $ref->getProperty('name');
        $nameProp->setAccessible(true);
        $nameProp->setValue($field, $name);

        return $field;
    }

    public function testFillFluentKeepsDotNotation()
    {
        $field = $this->makeFieldWithoutConstruct('nested.key');
        $field->fill(new Fluent(['nested' => ['key' => 'fluent-value']]));

        $this->assertSame('fluent-value', $field->value());
    }

    public function testFillEloquentUsesDataGetForRelationPath()
    {
        $profile = new class() extends Model
        {
            protected $guarded = [];
            protected $table = 'profiles';
        };
        $profile->forceFill(['first_name' => 'Jane']);

        $user = new class() extends Model
        {
            protected $guarded = [];
            protected $table = 'users';
        };
        $user->setRelation('profile', $profile);

        $field = $this->makeFieldWithoutConstruct('profile.first_name');
        $field->fill($user);

        $this->assertSame('Jane', $field->value());
    }

    public function testFillEloquentNullRelationYieldsNull()
    {
        $user = new class() extends Model
        {
            protected $guarded = [];
            protected $table = 'users';
        };
        $user->setRelation('profile', null);

        $field = $this->makeFieldWithoutConstruct('profile.first_name');
        $field->fill($user);

        $this->assertNull($field->value());
    }
}
