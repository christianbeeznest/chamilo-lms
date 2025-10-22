<?php

declare(strict_types=1);

/* For licensing terms, see /license.txt */

namespace Chamilo\CourseBundle\Component\CourseCopy;

use Chamilo\CourseBundle\Component\CourseCopy\Resources\Resource;
use UnserializeApi;

/**
 * A course-object to use in Export/Import/Backup/Copy.
 *
 * @author Bart Mollet <bart.mollet@hogent.be>
 */
class Course
{
    public array $resources;
    public string $code;
    public string $path;
    public ?string $destination_path = null;
    public ?string $destination_db = null;
    public string $encoding;
    public string $type;
    public string $backup_path = '';

    /** @var array<string,mixed> Legacy-friendly metadata bag (alias of $meta) */
    public array $info;

    /** @var array<string,mixed> Canonical metadata bag */
    public array $meta;

    /**
     * Create a new Course-object.
     */
    public function __construct()
    {
        $this->resources = [];
        $this->code = '';
        $this->path = '';
        $this->backup_path = '';
        $this->encoding = api_get_system_encoding();
        $this->type = '';

        // Keep $info and $meta in sync (alias)
        $this->info = [];
        $this->meta =& $this->info;
    }

    /**
     * Check if a resource links to the given resource.
     *
     * @param mixed $resource_to_check
     */
    public function is_linked_resource(&$resource_to_check): bool
    {
        foreach ($this->resources as $type => $resources) {
            if (\is_array($resources)) {
                foreach ($resources as $resource) {
                    Resource::setClassType($resource);
                    if ($resource->links_to($resource_to_check)) {
                        return true;
                    }
                    if (RESOURCE_LEARNPATH === $type && 'CourseCopyLearnpath' === $resource::class) {
                        if ($resource->has_item($resource_to_check)) {
                            return true;
                        }
                    }
                }
            }
        }

        return false;
    }

    /**
     * Add a resource from a given type to this course.
     */
    public function add_resource(&$resource): void
    {
        $this->resources[$resource->get_type()][$resource->get_id()] = $resource;
    }

    /**
     * Does this course have resources?
     *
     * @param int|null $type If provided, only check that type.
     */
    public function has_resources($type = null): bool
    {
        if (null !== $type) {
            return isset($this->resources[$type])
                && \is_array($this->resources[$type])
                && \count($this->resources[$type]) > 0;
        }

        return \count($this->resources) > 0;
    }

    public function show(): void
    {
        // no-op
    }

    /**
     * Returns sample text based on the imported course content.
     * This is used for language/encoding detection when metadata is missing.
     */
    public function get_sample_text(): string
    {
        $sample_text = '';

        foreach ($this->resources as $type => &$resources) {
            if (\count($resources) <= 0) {
                continue;
            }

            foreach ($resources as $id => &$resource) {
                $title = '';
                $description = '';

                switch ($type) {
                    case RESOURCE_ANNOUNCEMENT:
                    case RESOURCE_EVENT:
                    case RESOURCE_THEMATIC:
                    case RESOURCE_WIKI:
                        $title = $resource->title;
                        $description = $resource->content;
                        break;

                    case RESOURCE_DOCUMENT:
                        $title = $resource->title;
                        $description = $resource->comment;
                        break;

                    case RESOURCE_FORUM:
                    case RESOURCE_FORUMCATEGORY:
                    case RESOURCE_LINK:
                    case RESOURCE_LINKCATEGORY:
                    case RESOURCE_QUIZ:
                    case RESOURCE_TEST_CATEGORY:
                    case RESOURCE_WORK:
                        $title = $resource->title;
                        $description = $resource->description;
                        break;

                    case RESOURCE_FORUMPOST:
                        $title = $resource->title;
                        $description = $resource->text;
                        break;

                    case RESOURCE_SCORM:
                    case RESOURCE_FORUMTOPIC:
                        $title = $resource->title;
                        break;

                    case RESOURCE_GLOSSARY:
                    case RESOURCE_LEARNPATH:
                        $title = $resource->name;
                        $description = $resource->description;
                        break;

                    case RESOURCE_LEARNPATH_CATEGORY:
                        $title = $resource->name;
                        break;

                    case RESOURCE_QUIZQUESTION:
                        $title = $resource->question;
                        $description = $resource->description;
                        break;

                    case RESOURCE_SURVEY:
                        $title = $resource->title;
                        $description = $resource->subtitle;
                        break;

                    case RESOURCE_SURVEYQUESTION:
                        $title = $resource->survey_question;
                        $description = $resource->survey_question_comment;
                        break;

                    case RESOURCE_TOOL_INTRO:
                        $description = $resource->intro_text;
                        break;

                    case RESOURCE_ATTENDANCE:
                        $title = $resource->params['name'];
                        $description = $resource->params['description'];
                        break;

                    default:
                        break;
                }

                $title = api_html_to_text($title);
                $description = api_html_to_text($description);

                if ($title !== '') {
                    $sample_text .= $title . "\n";
                }
                if ($description !== '') {
                    $sample_text .= $description . "\n";
                }
                if ($title !== '' || $description !== '') {
                    $sample_text .= "\n";
                }
            }
        }

        return $sample_text;
    }

    /**
     * Converts to the system encoding all the language-sensitive fields in the imported course.
     */
    public function to_system_encoding(): void
    {
        foreach ($this->resources as $type => &$resources) {
            if (\count($resources) <= 0) {
                continue;
            }

            foreach ($resources as &$resource) {
                switch ($type) {
                    case RESOURCE_ANNOUNCEMENT:
                    case RESOURCE_EVENT:
                        $resource->title = api_to_system_encoding($resource->title, $this->encoding);
                        $resource->content = api_to_system_encoding($resource->content, $this->encoding);
                        break;

                    case RESOURCE_DOCUMENT:
                        $resource->title = api_to_system_encoding($resource->title, $this->encoding);
                        $resource->comment = api_to_system_encoding($resource->comment, $this->encoding);
                        break;

                    case RESOURCE_FORUM:
                    case RESOURCE_QUIZ:
                    case RESOURCE_FORUMCATEGORY:
                        if (isset($resource->title)) {
                            $resource->title = api_to_system_encoding($resource->title, $this->encoding);
                        }
                        if (isset($resource->description)) {
                            $resource->description = api_to_system_encoding($resource->description, $this->encoding);
                        }
                        if (isset($resource->obj)) {
                            foreach (['cat_title', 'cat_comment', 'title', 'description'] as $f) {
                                if (isset($resource->obj->{$f}) && \is_string($resource->obj->{$f})) {
                                    $resource->obj->{$f} = api_to_system_encoding($resource->obj->{$f}, $this->encoding);
                                }
                            }
                        }
                        break;

                    case RESOURCE_LINK:
                    case RESOURCE_LINKCATEGORY:
                    case RESOURCE_TEST_CATEGORY:
                        $resource->title = api_to_system_encoding($resource->title, $this->encoding);
                        $resource->description = api_to_system_encoding($resource->description, $this->encoding);
                        break;

                    case RESOURCE_FORUMPOST:
                        if (isset($resource->title)) {
                            $resource->title = api_to_system_encoding($resource->title, $this->encoding);
                        }
                        if (isset($resource->text)) {
                            $resource->text = api_to_system_encoding($resource->text, $this->encoding);
                        }
                        if (isset($resource->poster_name)) {
                            $resource->poster_name = api_to_system_encoding($resource->poster_name, $this->encoding);
                        }
                        break;

                    case RESOURCE_FORUMTOPIC:
                        if (isset($resource->title)) {
                            $resource->title = api_to_system_encoding($resource->title, $this->encoding);
                        }
                        if (isset($resource->topic_poster_name)) {
                            $resource->topic_poster_name = api_to_system_encoding($resource->topic_poster_name, $this->encoding);
                        }
                        if (isset($resource->title_qualify)) {
                            $resource->title_qualify = api_to_system_encoding($resource->title_qualify, $this->encoding);
                        }
                        break;

                    case RESOURCE_GLOSSARY:
                        $resource->name = api_to_system_encoding($resource->name, $this->encoding);
                        $resource->description = api_to_system_encoding($resource->description, $this->encoding);
                        break;

                    case RESOURCE_LEARNPATH:
                        $resource->name = api_to_system_encoding($resource->name, $this->encoding);
                        $resource->description = api_to_system_encoding($resource->description, $this->encoding);
                        $resource->content_maker = api_to_system_encoding($resource->content_maker, $this->encoding);
                        $resource->content_license = api_to_system_encoding($resource->content_license, $this->encoding);
                        break;

                    case RESOURCE_QUIZQUESTION:
                        $resource->question = api_to_system_encoding($resource->question, $this->encoding);
                        $resource->description = api_to_system_encoding($resource->description, $this->encoding);
                        if (\is_array($resource->answers) && \count($resource->answers) > 0) {
                            foreach ($resource->answers as &$answer) {
                                $answer['answer'] = api_to_system_encoding($answer['answer'], $this->encoding);
                                $answer['comment'] = api_to_system_encoding($answer['comment'], $this->encoding);
                            }
                        }
                        break;

                    case RESOURCE_SCORM:
                        $resource->title = api_to_system_encoding($resource->title, $this->encoding);
                        break;

                    case RESOURCE_SURVEY:
                        $resource->title = api_to_system_encoding($resource->title, $this->encoding);
                        $resource->subtitle = api_to_system_encoding($resource->subtitle, $this->encoding);
                        $resource->author = api_to_system_encoding($resource->author, $this->encoding);
                        $resource->intro = api_to_system_encoding($resource->intro, $this->encoding);
                        $resource->surveythanks = api_to_system_encoding($resource->surveythanks, $this->encoding);
                        break;

                    case RESOURCE_SURVEYQUESTION:
                        $resource->survey_question = api_to_system_encoding($resource->survey_question, $this->encoding);
                        $resource->survey_question_comment = api_to_system_encoding($resource->survey_question_comment, $this->encoding);
                        break;

                    case RESOURCE_TOOL_INTRO:
                        $resource->intro_text = api_to_system_encoding($resource->intro_text, $this->encoding);
                        break;

                    case RESOURCE_WIKI:
                        $resource->title = api_to_system_encoding($resource->title, $this->encoding);
                        $resource->content = api_to_system_encoding($resource->content, $this->encoding);
                        $resource->reflink = api_to_system_encoding($resource->reflink, $this->encoding);
                        break;

                    case RESOURCE_WORK:
                        $resource->url = api_to_system_encoding($resource->url, $this->encoding);
                        $resource->title = api_to_system_encoding($resource->title, $this->encoding);
                        $resource->description = api_to_system_encoding($resource->description, $this->encoding);
                        break;

                    default:
                        break;
                }
            }
        }

        $this->encoding = api_get_system_encoding();
    }

    /**
     * Serialize the course with the best serializer available (optionally compressed).
     */
    public static function serialize($course): string
    {
        $serialized = \extension_loaded('igbinary')
            ? igbinary_serialize($course)
            : serialize($course);

        // Compress if possible
        if (\function_exists('gzdeflate')) {
            $deflated = gzdeflate($serialized, 9);
            if ($deflated !== false) {
                $serialized = $deflated;
            }
        }

        return $serialized;
    }

    /**
     * Unserialize the course with the best serializer available.
     *
     * @return Course
     */
    public static function unserialize($course): Course
    {
        // Try to uncompress
        if (\function_exists('gzinflate')) {
            $inflated = @gzinflate($course);
            if ($inflated !== false) {
                $course = $inflated;
            }
        }

        $unserialized = \extension_loaded('igbinary')
            ? igbinary_unserialize($course)
            : UnserializeApi::unserialize('course', $course);

        /** @var Course $unserialized */
        return $unserialized;
    }
}
