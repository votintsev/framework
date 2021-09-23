<?php

namespace Illuminate\Tests\Integration\Database\EloquentHasOneWrongLoadTest;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Tests\Integration\Database\DatabaseTestCase;

/**
 * @group integration
 */
class EloquentHasOneWrongLoadTest extends DatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('members', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
        });

        Schema::create('signatures', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('member_id');
        });

        Schema::create('posts', function (Blueprint $table) {
            $table->increments('id');
            $table->string('title');
            $table->timestamps();
        });

        Schema::create('attachments', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('post_id')->nullable();
            $table->unsignedInteger('signature_id');
        });

        $alisa = Member::create([
            'name' => 'alisa'
        ]);
        $alisa->signature()->create();

        $bob = Member::create([
            'name' => 'bob'
        ]);
        $bob->signature()->create();

        $post = Post::create([
            'title' => 'attach_alice_signature'
        ]);
        $post->attachment()->create([
            'signature_id' => $alisa->signature->id
        ]);

        $post = Post::create([
            'title' => 'attach_bob_signature'
        ]);
        $post->attachment()->create([
            'signature_id' => $bob->signature->id
        ]);
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        Member::$bob = null;
    }

    public function testNotWorked()
    {
        $post = Post::whereHas('mySignatureAttachment')->get()->first();
        $this->assertEquals('attach_bob_signature', $post->title);
    }

    public function testWorkedIfPreload()
    {
        Member::getBob()->signature->id;

        $post = Post::whereHas('mySignatureAttachment')->get()->first();
        $this->assertEquals('attach_bob_signature', $post->title);
    }
}

class Member extends Model
{
    public static $bob = null;

    public $timestamps = false;

    protected $fillable = ['name'];

    public function signature()
    {
        return $this->hasOne(Signature::class);
    }

    public static function getBob()
    {
        if (! self::$bob) {
            self::$bob = Member::where('name', 'bob')->first();
        }

        return self::$bob;
    }
}
class Signature extends Model
{
    public $timestamps = false;
}

class Post extends Model
{
    protected $fillable = ['title'];

    public function attachment()
    {
        return $this->hasOne(Attachment::class);
    }

    /**
     * Assumed we use something like auth()->user()->signature->id
     * but for test proposes do call to static, it will show that we can change behaviour if preload relation without change this method.
     *
     * Problem, that src/Illuminate/Database/Eloquent/Relations/Relation.php at noConstraints method
     * set static::$constraints = false; and remove where statement for all children relation call...
     */
    public function mySignatureAttachment()
    {
        $bob = Member::getBob();

        \DB::connection()->enableQueryLog();
        $signatureId = $bob->signature->id;
        $queries = \DB::getQueryLog();

        dump($queries[0]['query'] ?? 'load before'); // will be 'select * from "signatures" limit 1'

        return $this->hasOne(Attachment::class)
                    ->where('signature_id', $signatureId);
    }
}

class Attachment extends Model
{
    public $timestamps = false;
    public $fillable = ['signature_id'];
}
