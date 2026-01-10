# Spam Detection Service Migration Plan

> **Status:** Planning Document
> **Created:** 2026-01-10
> **Purpose:** Clean migration dari current messy repo ke clean architecture
> **Priority:** HIGH - Repository sudah kacau, perlu restructuring

---

## 🚨 Current State Assessment

### Problems with Current Repo

```
❌ ISSUES:
1. Services terlalu coupled
2. No dependency injection
3. Hardcoded values everywhere
4. No proper abstraction layers
5. Test commands mixed with production code
6. No proper configuration management
7. Missing proper logging/auditing
```

### What's Good (Keep These)

```
✅ KEEP:
1. UnicodeDetector → Perfect, 100% accuracy
2. FuzzyMatcher → Solid algorithm
3. Spam detection test fixtures → Good test data
4. Algorithm design docs → Well documented
```

---

## 🎯 Migration Goals

### Clean Architecture Principles

```
1. Separation of Concerns
   ├─ Domain Layer (business logic)
   ├─ Application Layer (use cases)
   ├─ Infrastructure Layer (DB, external APIs)
   └─ Presentation Layer (controllers, commands)

2. Dependency Injection
   ├─ All services via constructor injection
   ├─ Interface-based programming
   └─ Service container binding

3. Configuration-Driven
   ├─ No hardcoded values
   ├─ All thresholds in config files
   └─ Environment-specific settings

4. Testable
   ├─ Unit tests for each service
   ├─ Integration tests for flows
   └─ Mockable dependencies
```

---

## 📦 New Directory Structure

### Clean Service Organization

```
app/
├─ Domain/
│  └─ SpamDetection/
│     ├─ Contracts/                    🆕
│     │  ├─ SpamDetectorInterface.php
│     │  ├─ ContextLoaderInterface.php
│     │  └─ SpamLoggerInterface.php
│     │
│     ├─ Entities/                     🆕
│     │  ├─ Comment.php
│     │  ├─ DetectionResult.php
│     │  ├─ SpamContext.php
│     │  └─ SpamCampaign.php
│     │
│     ├─ ValueObjects/                 🆕
│     │  ├─ SpamScore.php
│     │  ├─ SignalCollection.php
│     │  └─ Category.php (CRITICAL/MEDIUM/LOW)
│     │
│     └─ Exceptions/                   🆕
│        ├─ InvalidContextException.php
│        └─ DetectionFailedException.php
│
├─ Application/
│  └─ SpamDetection/
│     ├─ UseCases/                     🆕
│     │  ├─ DetectCommentSpam.php
│     │  ├─ LoadChannelContext.php
│     │  ├─ SaveDetectionLog.php
│     │  └─ AnalyzeCampaign.php
│     │
│     └─ DTOs/                         🆕
│        ├─ DetectionRequest.php
│        ├─ DetectionResponse.php
│        └─ ContextConfig.php
│
├─ Infrastructure/
│  └─ SpamDetection/
│     ├─ Detectors/                    MIGRATE HERE
│     │  ├─ UnicodeDetector.php       ✅
│     │  ├─ PatternAnalyzer.php       ✅ (modify)
│     │  ├─ ClusterDetector.php       ✅ (rename from SpamClusterDetector)
│     │  ├─ ContextualAnalyzer.php    ✅
│     │  └─ HybridDetector.php        ✅ (modify)
│     │
│     ├─ Repositories/                 🆕
│     │  ├─ ChannelContextRepository.php
│     │  └─ SpamDetectionLogRepository.php
│     │
│     ├─ Services/                     🆕
│     │  ├─ ContextLoader.php
│     │  ├─ SpamLogger.php
│     │  └─ DetectorFactory.php
│     │
│     └─ Utils/                        MIGRATE HERE
│        ├─ FuzzyMatcher.php          ✅
│        └─ TextNormalizer.php        🆕
│
└─ Presentation/
   ├─ Http/Controllers/
   │  └─ SpamDetectionController.php  🆕 (API endpoint)
   │
   └─ Console/Commands/
      └─ TestSpamDetection.php        MIGRATE HERE (rename)

tests/
├─ Unit/
│  └─ SpamDetection/
│     ├─ UnicodeDetectorTest.php      🆕
│     ├─ PatternAnalyzerTest.php      🆕
│     └─ ClusterDetectorTest.php      🆕
│
├─ Integration/
│  └─ SpamDetection/
│     └─ DetectionFlowTest.php        🆕
│
└─ Fixtures/
   └─ SpamDetection/
      ├─ complete_sample_comments_02.json  ✅
      └─ cluster_detection_test.json       ✅
```

---

## 🔄 Migration Steps

### Phase 1: Setup New Structure (Day 1)

```bash
# 1. Create new directories
mkdir -p app/Domain/SpamDetection/{Contracts,Entities,ValueObjects,Exceptions}
mkdir -p app/Application/SpamDetection/{UseCases,DTOs}
mkdir -p app/Infrastructure/SpamDetection/{Detectors,Repositories,Services,Utils}

# 2. Create interfaces (contracts)
touch app/Domain/SpamDetection/Contracts/SpamDetectorInterface.php
touch app/Domain/SpamDetection/Contracts/ContextLoaderInterface.php
touch app/Domain/SpamDetection/Contracts/SpamLoggerInterface.php

# 3. Create entities
touch app/Domain/SpamDetection/Entities/Comment.php
touch app/Domain/SpamDetection/Entities/DetectionResult.php
touch app/Domain/SpamDetection/Entities/SpamContext.php

# 4. Create value objects
touch app/Domain/SpamDetection/ValueObjects/SpamScore.php
touch app/Domain/SpamDetection/ValueObjects/Category.php
```

### Phase 2: Migrate Core Services (Day 2-3)

```bash
# 1. Copy detectors to new structure
cp app/Services/SpamDetection/UnicodeDetector.php \
   app/Infrastructure/SpamDetection/Detectors/

cp app/Services/SpamDetection/FuzzyMatcher.php \
   app/Infrastructure/SpamDetection/Utils/

# 2. Refactor PatternAnalyzer (remove hardcoded keywords)
# Before: private const MONEY_KEYWORDS = [...]
# After:  protected array $moneyKeywords (injected via config)

# 3. Refactor HybridDetector (add context awareness)
# Before: new HybridSpamDetector()
# After:  new HybridDetector($context, $detectors)

# 4. Create factory for detector creation
touch app/Infrastructure/SpamDetection/Services/DetectorFactory.php
```

### Phase 3: Database & Repositories (Day 4)

```bash
# 1. Create migrations
php artisan make:migration create_channel_spam_contexts_table
php artisan make:migration create_spam_detection_logs_table

# 2. Create repositories
touch app/Infrastructure/SpamDetection/Repositories/ChannelContextRepository.php
touch app/Infrastructure/SpamDetection/Repositories/SpamDetectionLogRepository.php

# 3. Seed default contexts
php artisan make:seeder ChannelSpamContextSeeder
```

### Phase 4: Use Cases & DTOs (Day 5)

```bash
# 1. Create use case classes
touch app/Application/SpamDetection/UseCases/DetectCommentSpam.php
touch app/Application/SpamDetection/UseCases/LoadChannelContext.php

# 2. Create DTOs for clean data transfer
touch app/Application/SpamDetection/DTOs/DetectionRequest.php
touch app/Application/SpamDetection/DTOs/DetectionResponse.php

# 3. Create service provider for bindings
php artisan make:provider SpamDetectionServiceProvider
```

### Phase 5: Configuration Files (Day 6)

```bash
# 1. Create config file
touch config/spam-detection.php

# Content:
return [
    'detectors' => [
        'unicode' => [
            'enabled' => true,
            'score' => 95,
        ],
        'cluster' => [
            'enabled' => true,
            'threshold' => env('SPAM_CLUSTER_THRESHOLD', 50),
            'min_similarity' => 0.7,
        ],
        'pattern' => [
            'enabled' => true,
            'keywords' => [
                'money' => ['menang', 'untung', 'cuan', ...],
                'urgency' => ['sekarang', 'buruan', ...],
            ],
        ],
    ],
    'scoring' => [
        'thresholds' => [
            'critical' => env('SPAM_CRITICAL_THRESHOLD', 70),
            'medium' => env('SPAM_MEDIUM_THRESHOLD', 40),
        ],
    ],
];
```

### Phase 6: Testing (Day 7)

```bash
# 1. Create unit tests
php artisan make:test Unit/SpamDetection/UnicodeDetectorTest
php artisan make:test Unit/SpamDetection/PatternAnalyzerTest
php artisan make:test Unit/SpamDetection/ClusterDetectorTest

# 2. Create integration tests
php artisan make:test Integration/SpamDetection/DetectionFlowTest

# 3. Run all tests
php artisan test --filter=SpamDetection
```

---

## 📋 Migration Checklist

### Pre-Migration

- [ ] Backup current database
- [ ] Document all current configurations
- [ ] List all dependent services
- [ ] Create feature branch: `feature/spam-detection-clean-architecture`

### During Migration

- [ ] Create new directory structure
- [ ] Implement interfaces/contracts
- [ ] Create entities and value objects
- [ ] Migrate detectors (one by one)
- [ ] Create repositories
- [ ] Implement use cases
- [ ] Create service provider
- [ ] Add configuration files
- [ ] Write unit tests
- [ ] Write integration tests

### Post-Migration

- [ ] Update documentation
- [ ] Remove old services (deprecate)
- [ ] Update dependent code
- [ ] Deploy to staging
- [ ] Run smoke tests
- [ ] Deploy to production
- [ ] Monitor for issues

---

## 🔧 Key Interfaces

### 1. SpamDetectorInterface

```php
namespace App\Domain\SpamDetection\Contracts;

use App\Domain\SpamDetection\Entities\Comment;
use App\Domain\SpamDetection\Entities\DetectionResult;

interface SpamDetectorInterface
{
    /**
     * Detect spam in a single comment.
     */
    public function detect(Comment $comment): DetectionResult;

    /**
     * Detect spam in batch comments.
     */
    public function detectBatch(array $comments): array;

    /**
     * Get detector name.
     */
    public function getName(): string;
}
```

### 2. ContextLoaderInterface

```php
namespace App\Domain\SpamDetection\Contracts;

use App\Domain\SpamDetection\Entities\SpamContext;

interface ContextLoaderInterface
{
    /**
     * Load spam context for a channel.
     */
    public function load(int $userPlatformId): SpamContext;

    /**
     * Save spam context for a channel.
     */
    public function save(SpamContext $context): void;

    /**
     * Get default context.
     */
    public function getDefault(): SpamContext;
}
```

### 3. SpamLoggerInterface

```php
namespace App\Domain\SpamDetection\Contracts;

use App\Domain\SpamDetection\Entities\DetectionResult;

interface SpamLoggerInterface
{
    /**
     * Log detection result.
     */
    public function log(DetectionResult $result): void;

    /**
     * Get detection logs for a channel.
     */
    public function getLogsForChannel(
        int $userPlatformId,
        int $limit = 100
    ): array;
}
```

---

## 🏭 Service Provider Example

```php
namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Domain\SpamDetection\Contracts\*;
use App\Infrastructure\SpamDetection\*;

class SpamDetectionServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Bind interfaces to implementations
        $this->app->bind(
            SpamDetectorInterface::class,
            HybridDetector::class
        );

        $this->app->bind(
            ContextLoaderInterface::class,
            ChannelContextRepository::class
        );

        $this->app->bind(
            SpamLoggerInterface::class,
            SpamDetectionLogRepository::class
        );

        // Singleton for factory
        $this->app->singleton(
            DetectorFactory::class,
            function ($app) {
                return new DetectorFactory(
                    $app->make(ContextLoaderInterface::class),
                    config('spam-detection')
                );
            }
        );
    }

    public function boot(): void
    {
        // Publish config
        $this->publishes([
            __DIR__.'/../../config/spam-detection.php'
                => config_path('spam-detection.php'),
        ], 'spam-detection-config');

        // Publish migrations
        $this->loadMigrationsFrom(
            __DIR__.'/../../database/migrations/spam_detection'
        );
    }
}
```

---

## 🧪 Testing Strategy

### Unit Tests (Isolated)

```php
// Test each detector independently
class UnicodeDetectorTest extends TestCase
{
    public function test_detects_fancy_unicode()
    {
        $detector = new UnicodeDetector();
        $result = $detector->detect(
            new Comment('𝐌0𝐍𝐀𝟒𝐃 test')
        );

        $this->assertTrue($result->isSpam());
        $this->assertEquals(95, $result->getScore()->getValue());
    }
}
```

### Integration Tests (Full Flow)

```php
// Test complete detection flow with context
class DetectionFlowTest extends TestCase
{
    public function test_automotive_context_whitelist()
    {
        // Setup automotive context
        $context = new SpamContext([
            'whitelist' => ['juta', 'cepat'],
            'blacklist' => ['M0NA4D'],
        ]);

        // Test legitimate car comment
        $useCase = new DetectCommentSpam(
            app(DetectorFactory::class),
            $context
        );

        $result = $useCase->execute(
            new Comment('Mobil ini cepat, harga 500 juta')
        );

        $this->assertFalse($result->isSpam());
    }
}
```

---

## 📊 Performance Considerations

### Optimization Strategies

```
1. Caching
   ├─ Cache channel contexts (Redis)
   ├─ Cache compiled patterns
   └─ Cache detection results (short TTL)

2. Batch Processing
   ├─ Process comments in chunks of 100
   ├─ Async job queues for large batches
   └─ Database bulk inserts for logs

3. Database Indexing
   ├─ Index user_platform_id
   ├─ Index video_id + created_at
   └─ Composite index for common queries

4. Monitoring
   ├─ Detection time metrics
   ├─ False positive rate
   └─ Memory usage
```

---

## 🚀 Deployment Strategy

### Gradual Rollout

```
Phase 1: Feature Flag (Week 1)
├─ Deploy new services alongside old
├─ 5% traffic to new system
└─ Monitor for errors

Phase 2: Increase Traffic (Week 2)
├─ 25% traffic to new system
├─ Compare results with old system
└─ Fix any discrepancies

Phase 3: Full Rollout (Week 3)
├─ 100% traffic to new system
├─ Deprecate old services
└─ Remove old code

Rollback Plan:
└─ Feature flag OFF = instant rollback to old system
```

---

## 📝 Documentation Updates

### Files to Update

```
1. Update README.md
   ├─ New architecture diagram
   ├─ Updated setup instructions
   └─ Configuration guide

2. Update API documentation
   ├─ New endpoints
   ├─ Request/response schemas
   └─ Error codes

3. Create admin guide
   ├─ How to configure contexts
   ├─ Interpreting detection logs
   └─ Troubleshooting

4. Developer documentation
   ├─ How to add new detectors
   ├─ How to extend contexts
   └─ Testing guide
```

---

## ⚠️ Breaking Changes

### API Changes

```php
// OLD (deprecated)
$detector = new HybridSpamDetector();
$result = $detector->detectBatch($comments);

// NEW (clean)
$useCase = app(DetectCommentSpam::class);
$result = $useCase->execute(
    DetectionRequest::fromArray([
        'comments' => $comments,
        'user_platform_id' => $channelId,
    ])
);
```

### Configuration Changes

```
OLD: Hardcoded in PatternAnalyzer::MONEY_KEYWORDS
NEW: config/spam-detection.php

OLD: SpamClusterDetector::SPAM_CAMPAIGN_THRESHOLD = 70
NEW: config('spam-detection.detectors.cluster.threshold')
```

---

## 🎯 Success Criteria

### Migration Complete When:

- [ ] All services moved to new structure
- [ ] 100% test coverage on core detectors
- [ ] Zero production errors for 1 week
- [ ] Performance same or better than old system
- [ ] All documentation updated
- [ ] Team trained on new architecture
- [ ] Old code removed from codebase

---

**MIGRATION TIMELINE: 2-3 Weeks**

Week 1: Setup + Core Migration
Week 2: Testing + Integration
Week 3: Deployment + Monitoring

---

**END OF MIGRATION PLAN**
