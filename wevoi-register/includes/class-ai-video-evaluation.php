<?php
/**
 * Retained Gemini video evaluation implementation (currently disabled).
 *
 * @package WeVoi_Register
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Gemini video evaluation implementation.
 */
class WeVoi_Register_AI_Video_Evaluation {

    const CRON_HOOK = 'wevoi_ai_evaluation_run';

    /** AI video evaluation is retained but disabled. */
    const ENABLED = false;

    /** Runtime threshold used only to flag suspiciously fast model responses. */
    const SUSPICIOUS_RESPONSE_MIN_ELAPSED_MS = 5000;

    /**
     * Entry point retained for a possible future reactivation.
     *
     * @param int $registration_id register_id.
     * @return array{triggered:bool, reason?:string, mode?:string, registration_id?:int}
     */
    public static function schedule_after_accept($registration_id) {
        $registration_id = (int) $registration_id;
        if ($registration_id < 1) {
            return array('triggered' => false, 'reason' => 'invalid_id');
        }

        if (!self::ENABLED) {
            return array(
                'triggered'       => false,
                'reason'          => 'ai_evaluation_disabled',
                'registration_id' => $registration_id,
            );
        }

        if (self::get_gemini_api_key() === '') {
            global $wpdb;
            self::record_evaluation_failure($wpdb, $registration_id, 'missing_gemini_api_key');
            return array(
                'triggered'         => false,
                'reason'            => 'missing_gemini_api_key',
                'gemini_elapsed_ms' => 0,
            );
        }

        ignore_user_abort(true);
        if (function_exists('set_time_limit')) {
            @set_time_limit(300);
        }

        $run = self::run_scheduled($registration_id);
        if (!is_array($run)) {
            $run = array(
                'completed'         => false,
                'gemini_elapsed_ms' => 0,
            );
        }

        return array_merge(
            array(
                'triggered'       => true,
                'mode'            => 'inline',
                'registration_id' => $registration_id,
            ),
            $run
        );
    }

    /**
     * Core evaluation: load registration, call Gemini, upsert DB row.
     * Still registered on {@see CRON_HOOK} for any legacy queued single events.
     *
     * @param int $registration_id register_id.
     */
    /**
     * @param int $registration_id register_id.
     * @return array{completed:bool,gemini_elapsed_ms:int,reason?:string}
     */
    public static function run_scheduled($registration_id) {
        $registration_id = (int) $registration_id;
        delete_transient('wevoi_ai_eval_sched_' . $registration_id);

        if ($registration_id < 1) {
            return self::build_run_result(0, false, 'invalid_id');
        }

        if (!self::ENABLED) {
            return self::build_run_result(0, false, 'ai_evaluation_disabled');
        }

        @set_time_limit(300);

        global $wpdb;

        if (self::get_gemini_api_key() === '') {
            self::log_error('AI evaluation skipped: missing Gemini API key.', array('registration_id' => $registration_id));
            self::record_evaluation_failure($wpdb, $registration_id, 'missing_gemini_api_key');
            return self::build_run_result(0, false, 'missing_gemini_api_key');
        }

        $payload = self::load_registration_payload($wpdb, $registration_id);
        if (!$payload) {
            self::log_error('AI evaluation: registration payload not found.', array('registration_id' => $registration_id));
            self::record_evaluation_failure($wpdb, $registration_id, 'registration_payload_not_found');
            return self::build_run_result(0, false, 'registration_payload_not_found');
        }

        $started = (int) (microtime(true) * 1000);
        $ctx     = self::build_context_from_payload($payload);

        $prompt  = self::build_evaluation_prompt($ctx);
        $request = self::build_gemini_request($ctx, $prompt);

        $model = self::get_gemini_model();
        $url   = 'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode($model) . ':generateContent';

        $response = wp_remote_post(
            $url,
            array(
                'headers' => array(
                    'Content-Type'    => 'application/json',
                    'x-goog-api-key' => self::get_gemini_api_key(),
                ),
                'body'    => wp_json_encode($request),
                'timeout' => 180,
            )
        );

        if (is_wp_error($response)) {
            self::log_error(
                'AI evaluation HTTP error: ' . $response->get_error_message(),
                array('registration_id' => $registration_id)
            );
            self::record_evaluation_failure($wpdb, $registration_id, 'http_error: ' . $response->get_error_message(), $ctx);
            return self::build_run_result($started, false, 'http_error');
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        $raw  = (string) wp_remote_retrieve_body($response);
        $data = json_decode($raw, true);

        if ($code < 200 || $code >= 300) {
            self::log_error(
                'AI evaluation Gemini error response.',
                array(
                    'registration_id' => $registration_id,
                    'http_code'       => $code,
                    'body_preview'    => mb_substr($raw, 0, 800),
                )
            );
            self::record_evaluation_failure(
                $wpdb,
                $registration_id,
                'gemini_http_' . $code . ': ' . mb_substr(wp_strip_all_tags($raw), 0, 400),
                $ctx
            );
            return self::build_run_result($started, false, 'gemini_http_' . $code);
        }

        if (!is_array($data)) {
            self::log_error('AI evaluation: invalid JSON from Gemini.', array('registration_id' => $registration_id));
            self::record_evaluation_failure($wpdb, $registration_id, 'invalid_json_response', $ctx);
            return self::build_run_result($started, false, 'invalid_json_response');
        }

        if (!empty($data['error'])) {
            self::log_error(
                'AI evaluation: Gemini API error object.',
                array(
                    'registration_id' => $registration_id,
                    'error'             => $data['error'],
                )
            );
            $err_msg = isset($data['error']['message']) ? (string) $data['error']['message'] : wp_json_encode($data['error']);
            self::record_evaluation_failure($wpdb, $registration_id, 'gemini_api_error: ' . mb_substr($err_msg, 0, 500), $ctx);
            return self::build_run_result($started, false, 'gemini_api_error');
        }

        $elapsed_ms = max(0, (int) round((microtime(true) * 1000) - $started));

        try {
            $normalized = self::parse_and_normalize_gemini_response($data, $elapsed_ms, $ctx);
        } catch (\Exception $e) {
            self::log_error(
                'AI evaluation parse error: ' . $e->getMessage(),
                array('registration_id' => $registration_id)
            );
            self::record_evaluation_failure($wpdb, $registration_id, 'parse_error: ' . $e->getMessage(), $ctx);
            return self::build_run_result($started, false, 'parse_error');
        }

        $save_body = self::map_normalized_to_save_body($normalized, $ctx, $model);
        $ok        = self::upsert_evaluation_row($wpdb, $save_body);
        if (!$ok) {
            self::log_error(
                'AI evaluation DB write failed.',
                array(
                    'registration_id' => $registration_id,
                    'db_error'        => $wpdb->last_error,
                )
            );
            self::record_evaluation_failure(
                $wpdb,
                $registration_id,
                'db_write_failed: ' . (string) $wpdb->last_error,
                $ctx
            );
            return self::build_run_result($started, false, 'db_write_failed');
        }

        return self::build_run_result($started, true);
    }

    /**
     * @param int    $started_ms microtime ms when Gemini request started (0 if never started).
     * @param bool   $completed  Whether evaluation was saved successfully.
     * @param string $reason     Optional failure reason.
     * @return array{completed:bool,gemini_elapsed_ms:int,reason?:string}
     */
    private static function build_run_result($started_ms, $completed, $reason = '') {
        $elapsed_ms = $started_ms > 0
            ? max(0, (int) round((microtime(true) * 1000) - $started_ms))
            : 0;

        $result = array(
            'completed'         => (bool) $completed,
            'gemini_elapsed_ms' => $elapsed_ms,
        );

        if ($reason !== '') {
            $result['reason'] = $reason;
        }

        return $result;
    }

    /**
     * @param wpdb $wpdb WordPress DB.
     * @param int  $registration_id register_id.
     * @return object|null|array
     */
    private static function load_registration_payload($wpdb, $registration_id) {
        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT r.register_id,
                        r.user_id,
                        r.competition_code,
                        r.youtube_link,
                        c.season_id,
                        c.competition_name,
                        c.competition_date,
                        c.competition_status,
                        ac.age_category,
                        ac.starting_age,
                        ac.ending_age,
                        t.topic
                 FROM {$wpdb->prefix}wevoi_registrations r
                 INNER JOIN {$wpdb->prefix}wevoi_competitions c ON r.competition_code = c.competition_code
                 LEFT JOIN {$wpdb->prefix}wevoi_age_categories ac ON r.age_category_id = ac.id
                 LEFT JOIN {$wpdb->prefix}wevoi_topics t ON t.competition_code = c.competition_code AND t.wevoi_age_category = ac.age_category
                 WHERE r.register_id = %d
                 LIMIT 1",
                $registration_id
            ),
            ARRAY_A
        );
    }

    /**
     * Persist a failed evaluation row so admins can see errors in DB (not only logs).
     *
     * @param wpdb                     $wpdb WordPress DB.
     * @param int                      $registration_id Registration id.
     * @param string                   $message Short machine-friendly reason.
     * @param array<string,mixed>|null $ctx Optional context with season_id, competition_code, user_id.
     */
    private static function record_evaluation_failure($wpdb, $registration_id, $message, $ctx = null) {
        $registration_id = (int) $registration_id;
        if ($registration_id < 1) {
            return;
        }

        $ids = is_array($ctx) ? $ctx : null;
        if (!is_array($ids) || empty($ids['user_id']) || empty($ids['competition_code'])) {
            $row = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT r.user_id, r.competition_code, c.season_id
                     FROM {$wpdb->prefix}wevoi_registrations r
                     INNER JOIN {$wpdb->prefix}wevoi_competitions c ON r.competition_code = c.competition_code
                     WHERE r.register_id = %d
                     LIMIT 1",
                    $registration_id
                ),
                ARRAY_A
            );
            if (!$row) {
                self::log_error(
                    'AI evaluation failure row skipped: registration not found.',
                    array(
                        'registration_id' => $registration_id,
                        'message'           => $message,
                    )
                );
                return;
            }
            $ids = array(
                'season_id'        => isset($row['season_id']) ? (int) $row['season_id'] : 0,
                'competition_code' => (int) $row['competition_code'],
                'user_id'          => (int) $row['user_id'],
            );
        } else {
            $ids = array(
                'season_id'        => isset($ids['season_id']) ? (int) $ids['season_id'] : 0,
                'competition_code' => (int) $ids['competition_code'],
                'user_id'          => (int) $ids['user_id'],
            );
        }

        $err   = mb_substr(wp_strip_all_tags((string) $message), 0, 2000);
        $model = self::get_gemini_model();

        $evaluated_video_url = '';
        if (is_array($ctx) && isset($ctx['youtube_url']) && trim((string) $ctx['youtube_url']) !== '') {
            $evaluated_video_url = self::normalize_youtube_file_uri((string) $ctx['youtube_url']);
        }
        if ($evaluated_video_url === '' && $registration_id > 0) {
            $yt_row = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT youtube_link FROM {$wpdb->prefix}wevoi_registrations WHERE register_id = %d LIMIT 1",
                    $registration_id
                )
            );
            if ($yt_row !== null && trim((string) $yt_row) !== '') {
                $evaluated_video_url = self::normalize_youtube_file_uri((string) $yt_row);
            }
        }

        $body = array(
            'season_id'                => $ids['season_id'],
            'competition_code'         => $ids['competition_code'],
            'user_id'                  => $ids['user_id'],
            'evaluated_video_url'      => $evaluated_video_url,
            'score_content'            => 0.0,
            'score_accuracy'           => 0.0,
            'score_delivery'            => 0.0,
            'score_organization'        => 0.0,
            'score_total'               => 0.0,
            'ai_model'                 => $model,
            'ai_version'                => 'v1',
            'status'                    => 'failed',
            'flags'                     => array(),
            'confidence'                => 0.0,
            'evaluation_json'           => array(
                'error'            => $err,
                'registration_id'  => $registration_id,
            ),
            'suspicious_fast_response'  => false,
            'likely_not_watched'        => true,
            'video_observed_reliably'   => false,
            'attempt_count'             => 1,
            'last_error'                => $err,
        );

        self::upsert_evaluation_row($wpdb, $body);
    }

    /**
     * @param array<string,mixed> $payload DB row.
     * @return array<string,mixed>
     */
    private static function build_context_from_payload(array $payload) {
        $rubric = array(
            'content'       => 25,
            'accuracy'      => 25,
            'delivery'      => 25,
            'organization'  => 25,
            'total'         => 100,
        );

        return array(
            'registration_id'   => (int) $payload['register_id'],
            'season_id'         => isset($payload['season_id']) ? (int) $payload['season_id'] : 0,
            'competition_code'  => (int) $payload['competition_code'],
            'user_id'           => (int) $payload['user_id'],
            'youtube_url'       => (string) $payload['youtube_link'],
            'competition_name'  => (string) $payload['competition_name'],
            'competition_status'=> (string) $payload['competition_status'],
            'competition_date'  => (string) $payload['competition_date'],
            'topic'             => isset($payload['topic']) ? (string) $payload['topic'] : '',
            'age_category'      => isset($payload['age_category']) ? (string) $payload['age_category'] : 'Unknown',
            'starting_age'      => isset($payload['starting_age']) ? $payload['starting_age'] : null,
            'ending_age'        => isset($payload['ending_age']) ? $payload['ending_age'] : null,
            'rubric'            => $rubric,
        );
    }

    /**
     * Narrative prompt for Gemini (unchanged rubric and instructions).
     *
     * @param array<string,mixed> $ctx Context.
     */
    private static function build_evaluation_prompt(array $ctx) {
        $age_category = $ctx['age_category'];
        $start_age    = $ctx['starting_age'] !== null && $ctx['starting_age'] !== '' ? (string) $ctx['starting_age'] : 'Unknown';
        $end_age      = $ctx['ending_age'] !== null && $ctx['ending_age'] !== '' ? (string) $ctx['ending_age'] : 'Unknown';
        $topic        = $ctx['topic'] !== '' ? $ctx['topic'] : 'Free Topic';
        $rubric_json  = wp_json_encode($ctx['rubric']);

        $comp_date = isset($ctx['competition_date']) && trim((string) $ctx['competition_date']) !== ''
            ? (string) $ctx['competition_date']
            : 'Unknown';
        $comp_status = isset($ctx['competition_status']) && trim((string) $ctx['competition_status']) !== ''
            ? (string) $ctx['competition_status']
            : 'Unknown';

        $comp_name = isset($ctx['competition_name']) ? (string) $ctx['competition_name'] : '';

        return <<<PROMPT
You are an expert WeVoi competition judge evaluating ONE recorded student video speech submission. Be fair, consistent, and age-appropriate.

EVALUATION MODE:
- Judge the performance as shown in the submitted video (on-camera presence, voice/clarity/fluency, language quality, structure, relevance to the assigned topic, and factual care apparent from what is said).
- Do not invent details you cannot reasonably observe from the video and any media supplied with this request. If the recording is unclear, partly unavailable, or hard to follow, record this in "flags" and lower "confidence".
- When a video file_data part is attached (YouTube URL supplied by the system), treat that video's audio and visuals as the primary evidence for scoring and feedback.
- Only if you still cannot consume that attached media should you use flags such as "video_not_accessible" with very low confidence.

AGE CALIBRATION:
- The student is in age category "{$age_category}" (ages {$start_age}–{$end_age}).
- Match expectations to this band: younger learners — prioritize clear message, confidence, and basic structure; older learners — also expect stronger reasoning, depth, and polish.

OFFICIAL RUBRIC (weights from the host; use these maxima when scoring):
{$rubric_json}

CRITERION MEANINGS (align scores and feedback to these):
- Content: relevance and development of ideas versus the stated topic, interest, appropriateness for the competition.
- Accuracy: factual soundness of claims as presented; note issues only when reasonably supported by the speech (avoid unverifiable accusations).
- Delivery: verbal and non-verbal delivery — articulation, pace, volume, confidence, eye contact, facial expressions, gestures, and body language where visible, overall engagement.
- For non-verbal communication (facial expressions, eye contact, gestures, posture), evaluate only what is clearly visible; if visibility is limited, mention this in feedback, add an appropriate flag (for example "nonverbal_not_clear"), and reduce confidence.
- Organization: clear structure (intro/body/conclusion or equivalent), logical flow, transitions, pacing and use of time.

OUTPUT RULES:
- Return ONLY one JSON object. No markdown, no code fences, no text before or after JSON.
- score_content, score_accuracy, score_delivery, score_organization: each a NUMBER from 0 up to its rubric maximum (defaults imply 0–25 each).
- score_total: NUMBER from 0 up to the rubric total (default 0–100) and should equal the sum of the four criterion scores.
- confidence: NUMBER from 0 to 1 (how certain you are given observable evidence).
- flags: ARRAY of short STRING tags for edge cases only when justified (examples: "audio_hard_to_hear", "partially_off_topic", "strong_delivery", "needs_clearer_structure", "nonverbal_not_clear").
- All feedback strings and flags MUST be in English. Be supportive and specific; critique the performance, not the person.

SUBMISSION CONTEXT:
- registration_id: {$ctx['registration_id']}
- Competition: {$comp_name}
- Competition code: {$ctx['competition_code']}
- Competition date: {$comp_date}
- Competition status: {$comp_status}
- Season ID: {$ctx['season_id']}
- User ID (internal): {$ctx['user_id']}
- Topic: {$topic}
- YouTube URL: {$ctx['youtube_url']}
- YouTube visibility may be Public or Unlisted. If the supplied media cannot actually be consumed, report that as a media-access issue and lower confidence.

OUTPUT JSON SCHEMA (field names and types — your entire reply must match this shape):
{
  "score_content": number,
  "score_accuracy": number,
  "score_delivery": number,
  "score_organization": number,
  "score_total": number,
  "feedback": {
    "content": string,
    "accuracy": string,
    "delivery": string,
    "organization": string,
    "overall": string
  },
  "confidence": number,
  "flags": string[]
}
PROMPT;
    }

    /**
     * @return array<string,mixed>
     */
    private static function response_schema() {
        return array(
            'type'       => 'OBJECT',
            'properties' => array(
                'score_content'       => array('type' => 'NUMBER'),
                'score_accuracy'      => array('type' => 'NUMBER'),
                'score_delivery'      => array('type' => 'NUMBER'),
                'score_organization'  => array('type' => 'NUMBER'),
                'score_total'         => array('type' => 'NUMBER'),
                'confidence'          => array('type' => 'NUMBER'),
                'flags'               => array(
                    'type'  => 'ARRAY',
                    'items' => array('type' => 'STRING'),
                ),
                'feedback'            => array(
                    'type'       => 'OBJECT',
                    'properties' => array(
                        'content'       => array('type' => 'STRING'),
                        'accuracy'      => array('type' => 'STRING'),
                        'delivery'      => array('type' => 'STRING'),
                        'organization'  => array('type' => 'STRING'),
                        'overall'       => array('type' => 'STRING'),
                    ),
                    'required'   => array('content', 'accuracy', 'delivery', 'organization', 'overall'),
                ),
            ),
            'required'   => array(
                'score_content',
                'score_accuracy',
                'score_delivery',
                'score_organization',
                'score_total',
                'confidence',
                'flags',
                'feedback',
            ),
        );
    }

    /**
     * @param array<string,mixed> $ctx Context.
     */
    private static function normalize_youtube_file_uri($youtube_url) {
        $u = trim((string) $youtube_url);
        if ($u === '') {
            return '';
        }
        $parsed = wp_parse_url($u);
        if (!is_array($parsed) || empty($parsed['host'])) {
            return $u;
        }
        $h = strtolower(preg_replace('/^www\./', '', $parsed['host']));
        if ($h === 'youtu.be' && !empty($parsed['path']) && strlen($parsed['path']) > 1) {
            $id = strtok(substr($parsed['path'], 1), '/?#');
            if ($id) {
                return 'https://www.youtube.com/watch?v=' . $id;
            }
        }
        if (in_array($h, array('youtube.com', 'm.youtube.com', 'music.youtube.com'), true)) {
            if (!empty($parsed['query'])) {
                parse_str($parsed['query'], $q);
                if (!empty($q['v'])) {
                    return 'https://www.youtube.com/watch?v=' . $q['v'];
                }
            }
            if (!empty($parsed['path']) && preg_match('#^/shorts/([^/?#]+)#', $parsed['path'], $m)) {
                return 'https://www.youtube.com/watch?v=' . $m[1];
            }
        }
        return $u;
    }

    /**
     * @param array<string,mixed> $ctx Context.
     * @return array<string,mixed>
     */
    private static function build_gemini_request(array $ctx, $prompt) {
        $include_video = true;
        $yt            = self::normalize_youtube_file_uri($ctx['youtube_url']);
        $parts         = array();
        if ($include_video && $yt !== '') {
            $parts[] = array(
                'file_data' => array(
                    'file_uri'  => $yt,
                    'mime_type' => 'video/mp4',
                ),
            );
        }
        $parts[] = array('text' => $prompt);

        return array(
            'contents'         => array(
                array(
                    'role'  => 'user',
                    'parts' => $parts,
                ),
            ),
            'generationConfig' => array(
                // 0 = most deterministic scoring; small run-to-run differences can still occur.
                'temperature'       => 0,
                'responseMimeType'  => 'application/json',
                'responseSchema'    => self::response_schema(),
            ),
        );
    }

    /**
     * @param array<string,mixed> $gemini_json Top-level decode of Gemini response.
     * @param int                 $elapsed_ms Elapsed milliseconds.
     * @param array<string,mixed> $ctx Context.
     * @return array<string,mixed>
     */
    private static function parse_and_normalize_gemini_response(array $gemini_json, $elapsed_ms, array $ctx) {
        $raw_text = '';
        $cands    = isset($gemini_json['candidates']) && is_array($gemini_json['candidates'])
            ? $gemini_json['candidates']
            : array();
        foreach ($cands as $c) {
            if (empty($c['content']['parts']) || !is_array($c['content']['parts'])) {
                continue;
            }
            foreach ($c['content']['parts'] as $p) {
                if (isset($p['text'])) {
                    $raw_text .= (string) $p['text'] . "\n";
                }
            }
        }
        $raw_text = trim($raw_text);
        if ($raw_text === '') {
            throw new \Exception('Gemini returned empty response text.');
        }

        $parsed = self::extract_json_object_from_text($raw_text);

        $score_content       = self::clamp((float) $parsed['score_content'], 0, 25);
        $score_accuracy      = self::clamp((float) $parsed['score_accuracy'], 0, 25);
        $score_delivery      = self::clamp((float) $parsed['score_delivery'], 0, 25);
        $score_organization  = self::clamp((float) $parsed['score_organization'], 0, 25);

        $score_total = isset($parsed['score_total']) ? (float) $parsed['score_total'] : ($score_content + $score_accuracy + $score_delivery + $score_organization);
        $score_total = self::clamp($score_total, 0, 100);

        $flags = self::normalize_flags(isset($parsed['flags']) ? $parsed['flags'] : array());
        $confidence = self::clamp((float) $parsed['confidence'], 0, 1);

        $ingestion_failure_flags = array(
            'video_not_accessible',
            'media_not_ingestible',
            'media_unavailable',
            'video_unavailable',
        );
        $likely_not_watched_by_flags = false;
        foreach ($flags as $f) {
            if (in_array($f, $ingestion_failure_flags, true)) {
                $likely_not_watched_by_flags = true;
                break;
            }
        }
        $suspicious_fast_response = ($elapsed_ms < self::SUSPICIOUS_RESPONSE_MIN_ELAPSED_MS);
        $likely_not_watched        = $likely_not_watched_by_flags || $suspicious_fast_response;

        if ($likely_not_watched && !in_array('nonverbal_not_clear', $flags, true)) {
            $flags[] = 'nonverbal_not_clear';
        }
        if ($likely_not_watched_by_flags && !in_array('video_not_accessible', $flags, true)) {
            $flags[] = 'video_not_accessible';
        }
        if ($suspicious_fast_response && !in_array('suspicious_fast_response', $flags, true)) {
            $flags[] = 'suspicious_fast_response';
        }
        if ($likely_not_watched) {
            $confidence = min($confidence, 0.35);
        }

        $evidence = array(
            'video_observed_reliably'   => ! $likely_not_watched,
            'likely_not_watched'        => $likely_not_watched,
            'likely_not_watched_by_flags' => $likely_not_watched_by_flags,
            'suspicious_fast_response'  => $suspicious_fast_response,
            'gemini_elapsed_ms'         => $elapsed_ms,
            'reason'                    => $likely_not_watched
                ? 'Media ingestion flags and/or very fast response indicate limited video evidence.'
                : 'No ingestion-failure signal and no suspiciously fast response detected.',
            'confidence_cap_applied'    => $likely_not_watched ? 0.35 : null,
        );

        $evaluation_json = array_merge(
            $parsed,
            array(
                'confidence' => $confidence,
                'flags'      => $flags,
                'normalized' => array(
                    'score_content'       => $score_content,
                    'score_accuracy'      => $score_accuracy,
                    'score_delivery'      => $score_delivery,
                    'score_organization'  => $score_organization,
                    'score_total'         => $score_total,
                    'confidence'          => $confidence,
                    'flags'               => $flags,
                ),
                'evidence'   => $evidence,
            )
        );

        return array(
            'season_id'                 => $ctx['season_id'],
            'competition_code'          => $ctx['competition_code'],
            'user_id'                   => $ctx['user_id'],
            'score_content'             => $score_content,
            'score_accuracy'            => $score_accuracy,
            'score_delivery'            => $score_delivery,
            'score_organization'        => $score_organization,
            'score_total'               => $score_total,
            'ai_model'                  => self::get_gemini_model(),
            'ai_version'                => 'v1',
            'status'                    => 'completed',
            'attempt_count'             => 1,
            'gemini_elapsed_ms'         => $elapsed_ms,
            'suspicious_fast_response'  => $suspicious_fast_response,
            'likely_not_watched'        => $likely_not_watched,
            'video_observed_reliably'   => ! $likely_not_watched,
            'flags'                     => $flags,
            'confidence'                => $confidence,
            'evaluation_json'           => $evaluation_json,
        );
    }

    /**
     * @param array<string,mixed> $normalized Parsed pipeline output.
     * @param array<string,mixed> $ctx Context.
     * @param string              $model Model id.
     * @return array<string,mixed>
     */
    private static function map_normalized_to_save_body(array $normalized, array $ctx, $model) {
        $video_snapshot = self::normalize_youtube_file_uri((string) $ctx['youtube_url']);

        return array(
            'season_id'                 => (int) $normalized['season_id'],
            'competition_code'          => (int) $normalized['competition_code'],
            'user_id'                   => (int) $normalized['user_id'],
            'evaluated_video_url'       => $video_snapshot,
            'score_content'             => (float) $normalized['score_content'],
            'score_accuracy'            => (float) $normalized['score_accuracy'],
            'score_delivery'            => (float) $normalized['score_delivery'],
            'score_organization'        => (float) $normalized['score_organization'],
            'score_total'               => (float) $normalized['score_total'],
            'ai_model'                  => (string) $model,
            'ai_version'                => 'v1',
            'status'                    => 'completed',
            'attempt_count'             => 1,
            'gemini_elapsed_ms'         => (int) $normalized['gemini_elapsed_ms'],
            'suspicious_fast_response'  => ! empty($normalized['suspicious_fast_response']),
            'likely_not_watched'        => ! empty($normalized['likely_not_watched']),
            'video_observed_reliably'   => ! empty($normalized['video_observed_reliably']),
            'flags'                     => $normalized['flags'],
            'confidence'                => (float) $normalized['confidence'],
            'evaluation_json'           => $normalized['evaluation_json'],
        );
    }

    /**
     * @param wpdb $wpdb DB.
     * @param array<string,mixed> $body Normalized evaluation fields for DB upsert.
     */
    private static function upsert_evaluation_row($wpdb, array $body) {
        $season_id         = (int) $body['season_id'];
        $competition_code  = (int) $body['competition_code'];
        $user_id           = (int) $body['user_id'];
        $score_content     = (float) $body['score_content'];
        $score_accuracy    = (float) $body['score_accuracy'];
        $score_delivery    = (float) $body['score_delivery'];
        $score_organization = (float) $body['score_organization'];
        $score_total       = isset($body['score_total'])
            ? (float) $body['score_total']
            : ($score_content + $score_accuracy + $score_delivery + $score_organization);

        $table_name = $wpdb->prefix . 'wevoi_ai_evaluation';

        $existing_id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$table_name} WHERE season_id = %d AND competition_code = %d AND user_id = %d LIMIT 1",
                $season_id,
                $competition_code,
                $user_id
            )
        );

        $raw_flags = isset($body['flags']) && is_array($body['flags']) ? $body['flags'] : array();
        $normalized_flags = array_values(
            array_unique(
                array_filter(
                    array_map(
                        static function ($flag) {
                            return sanitize_text_field((string) $flag);
                        },
                        $raw_flags
                    )
                )
            )
        );

        $status = isset($body['status']) && (string) $body['status'] !== ''
            ? sanitize_text_field((string) $body['status'])
            : 'completed';

        $attempt_count = isset($body['attempt_count']) ? max(1, (int) $body['attempt_count']) : 1;
        if ($existing_id && $status === 'failed') {
            $prev_attempt = (int) $wpdb->get_var(
                $wpdb->prepare("SELECT attempt_count FROM {$table_name} WHERE id = %d LIMIT 1", (int) $existing_id)
            );
            if ($prev_attempt > 0) {
                $attempt_count = $prev_attempt + 1;
            }
        }

        $last_error = null;
        if (isset($body['last_error']) && (string) $body['last_error'] !== '') {
            $last_error = mb_substr(wp_strip_all_tags((string) $body['last_error']), 0, 2000);
        }

        $evaluated_video_url = null;
        if (isset($body['evaluated_video_url']) && trim((string) $body['evaluated_video_url']) !== '') {
            $u = esc_url_raw(trim((string) $body['evaluated_video_url']));
            if ($u !== '') {
                $evaluated_video_url = strlen($u) <= 2048 ? $u : mb_substr($u, 0, 2048);
            }
        }

        $row_data = array(
            'season_id'                 => $season_id,
            'competition_code'          => $competition_code,
            'user_id'                   => $user_id,
            'evaluated_video_url'       => $evaluated_video_url,
            'score_content'             => $score_content,
            'score_accuracy'            => $score_accuracy,
            'score_delivery'            => $score_delivery,
            'score_organization'        => $score_organization,
            'score_total'               => $score_total,
            'ai_model'                  => isset($body['ai_model']) ? sanitize_text_field((string) $body['ai_model']) : null,
            'ai_version'                => isset($body['ai_version']) ? sanitize_text_field((string) $body['ai_version']) : null,
            'evaluation_json'           => wp_json_encode(isset($body['evaluation_json']) ? $body['evaluation_json'] : $body),
            'gemini_elapsed_ms'         => isset($body['gemini_elapsed_ms']) ? max(0, (int) $body['gemini_elapsed_ms']) : null,
            'suspicious_fast_response'  => ! empty($body['suspicious_fast_response']) ? 1 : 0,
            'likely_not_watched'        => ! empty($body['likely_not_watched']) ? 1 : 0,
            'video_observed_reliably'   => isset($body['video_observed_reliably']) ? (! empty($body['video_observed_reliably']) ? 1 : 0) : 1,
            'flags_json'                => wp_json_encode($normalized_flags),
            'confidence'                => isset($body['confidence']) ? max(0, min(1, (float) $body['confidence'])) : null,
            'attempt_count'             => $attempt_count,
            'status'                    => $status,
            'last_error'                => $last_error,
        );

        if ($existing_id) {
            $result = $wpdb->update($table_name, $row_data, array('id' => (int) $existing_id));
        } else {
            $result = $wpdb->insert($table_name, $row_data);
        }

        return $result !== false;
    }

    /**
     * @param mixed $text Raw model text.
     * @return array<string,mixed>
     */
    private static function extract_json_object_from_text($text) {
        $t = trim((string) $text);
        $t = preg_replace('/^```json\s*/i', '', $t);
        $t = preg_replace('/^```\s*/i', '', $t);
        $t = preg_replace('/\s*```$/', '', $t);
        $t = trim($t);

        $try = json_decode($t, true);
        if (is_array($try)) {
            return $try;
        }

        $start = strpos($t, '{');
        if ($start === false) {
            throw new \Exception('No JSON object found in model output.');
        }
        $depth = 0;
        $len   = strlen($t);
        for ($i = $start; $i < $len; $i++) {
            $ch = $t[ $i ];
            if ($ch === '{') {
                ++$depth;
            } elseif ($ch === '}') {
                --$depth;
                if ($depth === 0) {
                    $slice = substr($t, $start, $i - $start + 1);
                    $obj   = json_decode($slice, true);
                    if (is_array($obj)) {
                        return $obj;
                    }
                }
            }
        }
        throw new \Exception('Could not parse a valid JSON object from model output.');
    }

    /**
     * @param float $val Value.
     * @param float $min Min.
     * @param float $max Max.
     * @return float
     */
    private static function clamp($val, $min, $max) {
        if (! is_numeric($val)) {
            return $min;
        }
        $n = (float) $val;
        return min($max, max($min, $n));
    }

    /**
     * @param mixed $flags Flags.
     * @return array<int,string>
     */
    private static function normalize_flags($flags) {
        if (! is_array($flags)) {
            return array();
        }
        $out = array();
        foreach ($flags as $f) {
            $s = trim((string) $f);
            if ($s !== '') {
                $out[] = $s;
            }
        }
        return array_values(array_unique($out));
    }

    /**
     * @return string
     */
    private static function get_gemini_api_key() {
        $stored_value = get_option('wevoi_register_gemini_api_key', '');
        if ($stored_value === '' || $stored_value === null) {
            return '';
        }
        $stored_value = (string) $stored_value;
        if (class_exists('WeVoi_Register_Encryption_Handler')) {
            $encryption_handler = WeVoi_Register_Encryption_Handler::instance();
            if ($encryption_handler && $encryption_handler->is_encryption_ready()) {
                $decrypted = $encryption_handler->decrypt($stored_value);
                if (is_string($decrypted) && $decrypted !== '') {
                    return trim($decrypted);
                }
                // Wrong key / corrupted ciphertext: never send encrypted blob to Google as "API key".
                return '';
            }
        }
        return trim($stored_value);
    }

    /**
     * @return string
     */
    private static function get_gemini_model() {
        $m = trim((string) get_option('wevoi_register_gemini_model', 'gemini-2.5-flash'));
        return $m !== '' ? $m : 'gemini-2.5-flash';
    }

    /**
     * @param string              $message Message.
     * @param array<string,mixed> $context Context.
     */
    private static function log_error($message, array $context = array()) {
        if (class_exists('WeVoi_Register_Logger')) {
            WeVoi_Register_Logger::instance()->log_error($message, $context);
        } else {
            error_log('[WeVoi AI] ' . $message . ' ' . wp_json_encode($context));
        }
    }
}

add_action(WeVoi_Register_AI_Video_Evaluation::CRON_HOOK, array('WeVoi_Register_AI_Video_Evaluation', 'run_scheduled'), 10, 1);
