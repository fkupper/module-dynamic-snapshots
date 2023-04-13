<?php

namespace Fkupper\Lib\Generator;

use Codeception\Lib\Generator\Snapshot;
use Codeception\Util\Shared\Namespaces;

class DynamicSnapshot extends Snapshot
{
    use Namespaces;

    protected string $template = <<<EOF
<?php

namespace {{namespace}};

use Fkupper\Codeception\DynamicSnapshot;

class {{name}} extends DynamicSnapshot
{

{{actions}}

    protected function fetchDynamicData()
    {
        // TODO: return a value which will be used for snapshot
        // OPTIONALLY: set the substitutions
        \$this->setSubstitutions([
            'key' => 'value',
        ]);
    }
}
EOF;

    protected string $actionsTemplate = <<<EOF
    /**
     * @var \\{{actorClass}};
     */
    protected \${{actor}};

    public function __construct(\\{{actorClass}} \$I)
    {
        \$this->{{actor}} = \$I;
    }
EOF;
}
