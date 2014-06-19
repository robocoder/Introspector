<?php
/**
 *               ___
 *         _..--"\  `|`""--.._
 *      .-'       \  |        `'-.
 *     /           \_|___...----'`\
 *    |__,,..--""``(_)--..__      |
 *    '\     _.--'`.I._     ''--..'
 *      `''"`,#JGS/_|_\###,---'`
 *        ,#'  _.:`___`:-._ '#,
 *       #'  ,~'-;(oIo);-'~, '#
 *       #   `~-(  |    )=~`  #
 *       #       | |_  |      #
 *       #       ; ._. ;      #
 *       #  _..-;|\ - /|;-._  #
 *       #-'   /_ \\_// _\  '-#
 *     /`#    ; /__\-'__\;    #`\
 *    ;  #\.--|  |O  O   |'-./#  ;
 *    |__#/   \ _;O__O___/   \#__|
 *     | #\    [I_[_]__I]    /# |
 *     \_(#   /  |O  O   \   #)_/
 *           /   |        \
 *          /    |         \
 *         /    /\          \
 *        /     | `\         ;
 *       ;      \   '.       |
 *        \-._.__\     \_..-'/
 *         '.\  \-.._.-/  /'`
 *            \_.\    /._/
 *             \_.;  ;._/
 *           .-'-./  \.-'-.
 *          (___.'    '.___)
 */

namespace VIPSoft\Introspector;

/**
 * @copyright 2014 Anthon Pang
 * @license Apache 2.0
 * @author Anthon Pang <anthon.pang@gmail.com>
 */
class Gadget
{
    private $capture;

    /**
     * Constructor
     */ 
    public function __construct()
    {
    }

    /**
     * Begin capturing
     */
    public function startCapture()
    {
        $this->capture = array(
            'extensions' => get_loaded_extensions(),
            'files'      => get_included_files(),
            'classes'    => get_declared_classes(),
            'interfaces' => get_declared_interfaces(),
            'constants'  => get_defined_constants(),
            'variables'  => get_defined_vars(),
            'functions'  => get_defined_functions(),
        );
    }

    /**
     * End capturing
     *
     * @return array
     */
    public function endCapture()
    {
        if ( ! $this->capture) {
            return array(
                'extensions' => array(),
                'files'      => array(),
                'classes'    => array(),
                'interfaces' => array(),
                'constants'  => array(),
                'functions'  => array(
                    'internal' => array(),
                    'user'     => array(),
                ),
                'variables'  => array(),
            );
        }

        // we can't use a local variable in the scope of the get_defined_vars() call
        $this->capture['_functions'] = get_defined_functions();

        $temp = array(
            'extensions' => array_diff(get_loaded_extensions(), $this->capture['extensions']),
            'files'      => array_diff(get_included_files(), $this->capture['files']),
            'classes'    => array_diff(get_declared_classes(), $this->capture['classes']),
            'interfaces' => array_diff(get_declared_interfaces(), $this->capture['interfaces']),
            'constants'  => array_diff(get_defined_constants(), $this->capture['constants']),
            'variables'  => array_diff(get_defined_vars(), $this->capture['variables']),
            'functions'  => array(
                'internal' => array_diff($this->capture['_functions']['internal'], $this->capture['functions']['internal']),
                'user'     => array_diff($this->capture['_functions']['user'], $this->capture['functions']['user']),
            ),
        );

        $this->capture = null;

        return $temp;
    }

    /**
     * Describe named class, interface, trait, ...
     *
     * @param string $name
     *
     * @return array
     *
     * {@internal: Does not examine PHP doc blocks. }}
     */
    public function describe($name)
    {
        $metadata = array();

        if (is_object($name)) {
            $obj  = $name;
            $name = get_class($name);

            try {
                $info = $this->describeObject($obj);

                if ($info) {
                    $metadata['object'] = array(
                        'name'       => $name,
                        'properties' => $info['properties'],
                    );
                }
            } catch (\ReflectionException $e) {
            }
        }

        if (class_exists($name)) {
            try {
                $reflection = new \ReflectionClass($name);
                $info       = $this->describeClass($reflection);

                if ($info) {
                    $metadata['class'] = array(
                        'name'       => $name,
                        'parent'     => $info['parent'],
                        'interfaces' => $info['interfaces'],
                        'constants'  => $info['constants'],
                        'properties' => $info['properties'],
                        'methods'    => $info['methods'],
                        'traits'     => $info['traits'],
                    );
                }
            } catch (\ReflectionException $e) {
            }
        }

        if (interface_exists($name)) {
            try {
                $reflection = new \ReflectionClass($name);
                $info       = $this->describeClass($reflection);

                if ($info) {
                    $metadata['interface'] = array(
                        'name'       => $name,
                        'interfaces' => $info['interfaces'],
                        'constants'  => $info['constants'],
                        'methods'    => $info['methods'],
                    );
                }
            } catch (\ReflectionException $e) {
            }
        }

        if (trait_exists($name)) {
            try {
                $reflection = new \ReflectionClass($name);
                $info       = $this->describeClass($reflection);

                if ($info) {
                    $metadata['trait'] = array(
                        'name'       => $name,
                        'properties' => $info['properties'],
                        'methods'    => $info['methods'],
                        'traits'     => $info['traits'],
                    );
                }
            } catch (\ReflectionException $e) {
            }
        }

        if (function_exists($name)) {
            try {
                $reflection = new \ReflectionFunction($name);
                $info       = $this->describeFunction($reflection);

                if ($info) {
                    $metadata['function'] = array(
                        'name'   => $name,
                        'params' => $info['params'],
                    );
                }
            } catch (\ReflectionException $e) {
            }
        }

        if (extension_loaded($name)) {
            try {
                $reflection = new \ReflectionExtension($name);
                $info       = $this->describeExtension($reflection);

                if ($info) {
                    $metadata['extension'] = array(
                        'name'      => $name,
                        'classes'   => $info['classes'],
                        'constants' => $info['constants'],
                        'functions' => $info['functions'],
                        'version'   => $info['version'],
                    );
                }
            } catch (\ReflectionException $e) {
            }
        }

        return $metadata;
    }

    private function translateModifiers($modifierMask)
    {
        $modifiers = array();

        if ($modifierMask & \ReflectionMethod::IS_STATIC) {
            $modifiers[] = 'static';
        }

        if ($modifierMask & \ReflectionMethod::IS_PUBLIC) {
            $modifiers[] = 'public';
        }

        if ($modifierMask & \ReflectionMethod::IS_PROTECTED) {
            $modifiers[] = 'protected';
        }

        if ($modifierMask & \ReflectionMethod::IS_PRIVATE) {
            $modifiers[] = 'private';
        }

        if ($modifierMask & \ReflectionMethod::IS_ABSTRACT) {
            $modifiers[] = 'abstract';
        }

        if ($modifierMask & \ReflectionMethod::IS_FINAL) {
            $modifiers[] = 'final';
        }

        return implode(' ', $modifiers);
    }

    /**
     * Describe object
     *
     * @param mixed $object
     *
     * @return array
     */
    private function describeObject($object)
    {
        try {
            $reflection = new \ReflectionClass($object);

            foreach ($reflection->getProperties() as $property) {
                $modifiers = $property->getModifiers();

                $property->setAccessible(true);

                $properties[$property->getName()] = array(
                    'accessibility' => $this->translateModifiers($modifiers),
                    'value' => $property->getValue($object),
                );
            }

            return array(
                'properties' => $properties,
            );
        } catch (\ReflectionException $e) {
        }
    }

    /**
     * Describe class
     *
     * @param \ReflectionClass $class
     *
     * @return array
     */
    private function describeClass(\ReflectionClass $class)
    {
        try {
            $parent     = $class->getParentClass();
            $interfaces = $class->getInterfaceNames();
            $constants  = $class->getConstants();
            $properties = $class->getDefaultProperties();

            foreach ($class->getMethods() as $method) {
                $methods[$method->getName()] = $this->describeFunction($method);
            }

            $traits = array_map(
                function (\ReflectionClass $property) {
                    return $property->getName();
                },
                $class->getTraits()
            );

            return array(
                'parent' => $parent,
                'interfaces' => $interfaces,
                'constants' => $constants,
                'properties' => $properties,
                'methods' => $methods,
                'traits' => $traits,
            );
        } catch (\ReflectionException $e) {
        }
    }

    /**
     * Describe function
     *
     * @param \ReflectionFunctionAbstract $function
     *
     * @return array
     */
    private function describeFunction(\ReflectionFunctionAbstract $function)
    {
        try {
            $parameters = $function->getParameters();
            $params     = array();

            foreach ($parameters as $parameter) {
                $params[] = array(
                    'hint'    => $parameter->isArray() ? 'array'
                               : ($parameter->isCallable() ? 'callable'
                               : (is_object($parameter->getClass()) ? $parameter->getClass()->getName()
                               : null)),
                    'byRef'   => $parameter->isPassedByReference(),
                    'name'    => $parameter->getName(),
                    'default' => $parameter->isDefaultValueAvailable() ?
                                 ($parameter->getDefaultValueConstantName() ? $parameter->getDefaultValueConstantName()
                               : $parameter->getDefaultValue())
                               : null,
                );
            }

            return array(
                'params' => $params,
                'accessibility' => $function instanceof \ReflectionMethod ? $this->translateModifiers($function->getModifiers())
                                 : null,
            );
        } catch (\ReflectionException $e) {
        }
    }

    private function describeExtension(\ReflectionExtension $extension)
    {
        try {
            $classes   = $extension->getClassNames();
            $constants = $extension->getConstants();
            $version   = $extension->getVersion();

            $functions = array_map(
                function (\ReflectionFunction $function) {
                    return $function->getName();
                },
                $extension->getFunctions()
            );

            return array(
                'classes'   => $classes,
                'constants' => $constants,
                'functions' => $functions,
                'version'   => $version,
            );
        } catch (\ReflectionException $e) {
        }
    }
}
