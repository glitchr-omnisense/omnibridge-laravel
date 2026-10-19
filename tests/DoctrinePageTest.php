<?php

namespace Omnibridge\Laravel\Tests;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Illuminate\Http\Request;
use Omnibridge\Laravel\Doctrine\Connections;
use Omnibridge\Laravel\Form\FormRenderer;
use Omnibridge\Laravel\Form\FormView;
use Omnibridge\Laravel\Page\AppVariable;
use Omnibridge\Laravel\Page\Site;
use Omnibridge\Laravel\Page\Theme;
use Omnibridge\Laravel\Parameters\DatabaseSettings;
use App\Entity\Destination;
use Omnibridge\Laravel\View\Breakpoints;
use Omnibridge\Laravel\View\Encore;

/** Doctrine on Laravel's connection, and the pieces an omnibase page asks of its framework. */
final class DoctrinePageTest extends TestCase
{
    public function testDoctrineOpensLaravelsConnection(): void
    {
        $this->assertSame(['driver' => 'pdo_mysql', 'host' => 'database', 'port' => 3306, 'dbname' => 'monsieur', 'user' => 'user', 'password' => 'password', 'charset' => 'utf8mb4'],
            Connections::parameters(['driver' => 'mysql', 'host' => 'database', 'port' => '3306', 'database' => 'monsieur', 'username' => 'user', 'password' => 'password']));
        $this->assertSame(['driver' => 'pdo_sqlite', 'memory' => true], Connections::parameters(['driver' => 'sqlite', 'database' => ':memory:']));

        \DB::table('migrations_probe')->getConnection()->statement('CREATE TABLE probe (id INTEGER)');
        \DB::insert('INSERT INTO probe (id) VALUES (7)');
        $this->assertSame(7, (int) $this->app->make(Connection::class)->fetchOne('SELECT id FROM probe'), 'the same database as Laravel\'s');
    }

    public function testTheEntityManagerNamesTablesAsOmnibaseDoes(): void
    {
        $entities = $this->app->make(EntityManagerInterface::class);
        $this->assertSame($entities, $this->app->make(EntityManagerInterface::class));
        $metadata = $entities->getClassMetadata(Destination::class);
        $this->assertSame('destination', $metadata->getTableName());
        (new SchemaTool($entities))->createSchema([$metadata]);

        $destination = new Destination();
        $destination->slug = 'japon';
        $destination->startedAt = new \DateTime('2026-04-10 18:05:00', new \DateTimeZone('Asia/Tokyo'));
        $entities->persist($destination);
        $entities->flush();
        $entities->clear();

        $read = $entities->getRepository(Destination::class)->findOneBy(['slug' => 'japon']);
        $this->assertSame('2026-04-10 09:05:00', \DB::table('destination')->value('startedAt'), 'stored in UTC');
        $this->assertEquals(new \DateTime('2026-04-10 09:05:00', new \DateTimeZone('UTC')), $read->startedAt);
    }

    public function testTheSiteIsReadInItsSettings(): void
    {
        $this->settings([
            'base.settings.title' => [null, ['fr-FR' => 'Les Ringo Stars', 'en-GB' => 'The Ringo Stars']],
            'base.settings.slogan' => [null, ['en-GB' => 'Apple stars']],
            'base.settings.logo' => [null, ['fr-FR' => '41e865aa-fd2b-460f-92f5-d4fd713e999a']],
            'base.settings.meta.description' => [null, ['fr-FR' => 'Un blog']],
            'base.settings.launchdate' => [null, ['fr-FR' => ['date' => '2019-01-01 00:00:00']]],
        ]);
        $site = new Site($this->app->make(DatabaseSettings::class), 'fr', static fn (string $uuid) => '/uploads/'.$uuid);

        $this->assertSame('Les Ringo Stars', $site->title(), 'the default language\'s value');
        $this->assertSame('Apple stars', $site->slogan(), 'else any');
        $this->assertSame('/uploads/41e865aa-fd2b-460f-92f5-d4fd713e999a', $site->logo());
        $this->assertSame(['_self' => 'Un blog'], $site->meta()['description']);
        $this->assertSame('2019-'.date('Y'), $site->age());

        $app = AppVariable::of(Request::create('/japon/articles?page=2'), 'fr_FR', $site, new Theme(['dark' => ['id' => 'dark']], [], 'dark', null, ['id' => null]));
        $this->assertSame('fr_FR', $app->request->getLocale());
        $this->assertSame('/japon/articles', $app->request->getPathInfo());
        $this->assertTrue($app->session->started);
        $this->assertSame(['id' => 'dark'], $app->theme->mode());
    }

    public function testEncoreTagsAndTheBreakpoints(): void
    {
        $build = ['entrypoints' => self::FIXTURES.'/build/entrypoints.json', 'manifest' => self::FIXTURES.'/build/manifest.json'];
        $encore = new Encore([$build], self::FIXTURES.'/public', ['mobile' => 'all and (max-width: 470px)']);

        $this->assertSame('<link rel="stylesheet" href="/assets/app-async.css" >'."\n".'<link rel="stylesheet" href="/assets/app-async-mobile.css" media="all and (max-width: 470px)">'."\n", $encore->linkTags('app'));
        $this->assertSame('<script src="/assets/runtime.js" defer=""></script>'."\n".'<script src="/assets/app-async.js" defer=""></script>'."\n".'<script src="/assets/app-defer.js" defer=""></script>'."\n", $encore->scriptTags('app'));
        $this->assertSame('', $encore->scriptTags('app'), 'a file is printed once per page');

        $html = Breakpoints::inject('<html><head><link rel="stylesheet" href="/a.css"></head></html>', ['mobile' => 'all and (max-width: 470px)']);
        $this->assertStringStartsWith('<html><head><style>body:before {', $html);
        $this->assertStringContainsString("@media all and (max-width: 470px) {\n    body:before {\n        content: \"mobile\";", $html);
        $this->assertSame('<p>no head</p>', Breakpoints::inject('<p>no head</p>', ['mobile' => 'x']));
    }

    public function testASimpleFormInOmnibasesMarkup(): void
    {
        $form = (new FormView('newsletter_subscriber'))
            ->add('email', ['type' => 'email', 'required' => true])
            ->add('_csrf_token', ['type' => 'hidden']);
        $renderer = new FormRenderer($this->app->make(\Omnibridge\Contract\Translations::class));

        $html = $renderer->start($form).$renderer->row($form['email']).$renderer->end($form);
        $this->assertStringStartsWith('<form name="newsletter_subscriber" method="post">', $html);
        $this->assertStringContainsString('type="email" id="newsletter_subscriber_email" name="newsletter_subscriber[email]" required="required" class="form-control"', $html);
        $this->assertStringEndsWith('<input type="hidden" id="newsletter_subscriber__csrf_token" name="newsletter_subscriber[_csrf_token]" /></form>', $html);
    }
}
