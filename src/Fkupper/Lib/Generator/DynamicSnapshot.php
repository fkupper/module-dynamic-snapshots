<?php

namespace Fkupper\Lib\Generator;

use Codeception\Lib\Generator\Snapshot;
use Codeception\Util\Shared\Namespaces;

class DynamicSnapshot extends Snapshot
{
    use Namespaces;

    protected $template = <<<EOF
<?php
namespace {{namespace}};

class {{name}} extends \\Codeception\\DynamicSnapshot
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

    protected $actionsTemplate = <<<EOF
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
