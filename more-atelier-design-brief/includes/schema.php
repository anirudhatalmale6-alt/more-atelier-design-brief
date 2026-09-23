<?php
/**
 * One source of truth for the brief.
 *
 * The form markup and the email are both generated from this array, so a field
 * can never exist on screen but go missing from the enquiry that lands in the
 * inbox. Order here is the order in both places.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

function madb_schema() {
	return array(

		array(
			'panel' => 'welcome',
			'heading' => 'Design Brief',
			'lede' => array(
				'Thank you for considering More Atelier.',
				'Every project begins a little differently. Some clients come to us with plans already underway, while others are at the very beginning with an idea of what they would like to create.',
				'This short project brief helps us understand where things currently sit, what you would like to achieve, and how we may be able to support you. There are no wrong answers and nothing needs to be completely resolved at this stage — simply share what you know so far.',
			),
		),

		/* ---------------------------------------------------------- 01 */
		array(
			'panel' => 'intro', 'num' => '01.', 'title' => 'The Project',
			'heading' => 'Your Project',
			'lede' => array( 'Every project begins differently. This first section gives us a little context around what you’re planning, where the project is located and what you’re hoping to create.' ),
		),
		array(
			'panel' => 'fields', 'num' => '01.', 'title' => 'The Project',
			'heading' => 'About You',
			'section' => '01. The Project',
			'fields' => array(
				array( 'name' => 'name',   'label' => 'First & last name', 'type' => 'text', 'required' => true, 'autocomplete' => 'name' ),
				array( 'name' => 'email',  'label' => 'Email address',     'type' => 'email', 'required' => true, 'autocomplete' => 'email' ),
				array( 'name' => 'mobile', 'label' => 'Mobile number',     'type' => 'tel',  'autocomplete' => 'tel' ),
			),
		),
		array(
			'panel' => 'fields', 'num' => '01.', 'title' => 'The Project',
			'heading' => 'The Project',
			'section' => '01. The Project',
			'fields' => array(
				array( 'name' => 'location', 'label' => 'Project location', 'type' => 'text' ),
				array( 'name' => 'project_type', 'label' => 'What type of project', 'type' => 'radio',
					'options' => array( 'Residential', 'Hospitality', 'Retail', 'Commercial', 'Other' ) ),
			),
		),
		array(
			'panel' => 'fields', 'num' => '01.', 'title' => 'The Project',
			'heading' => 'About the Project',
			'section' => '01. The Project',
			'fields' => array(
				array( 'name' => 'about_project', 'label' => 'Tell us a little about your project', 'type' => 'textarea' ),
			),
		),

		/* ---------------------------------------------------------- 02 */
		array(
			'panel' => 'intro', 'num' => '02.', 'title' => 'Project Status',
			'heading' => 'Project Status',
			'lede' => array( 'Understanding where your project currently sits helps us see what is already in place and where More Atelier would step in. Tell us about any plans, consultants, builders or other project teams already involved, and where you may still need support.' ),
		),
		array(
			'panel' => 'fields', 'num' => '02.', 'title' => 'Project Status',
			'heading' => 'Project Stage',
			'section' => '02. Project Status',
			'fields' => array(
				array( 'name' => 'project_stage', 'label' => 'What stage is the project currently at', 'type' => 'radio',
					'options' => array( 'Early planning', 'Architectural design underway', 'Plans completed', 'Builder / contractor appointed', 'Construction underway', 'Other' ) ),
			),
		),
		array(
			'panel' => 'fields', 'num' => '02.', 'title' => 'Project Plan',
			'heading' => 'Project Planning',
			'section' => '02. Project Status',
			'fields' => array(
				array( 'name' => 'ideal_completion', 'label' => 'When would you ideally like the project completed', 'type' => 'text' ),
				array( 'name' => 'existing_plans', 'label' => 'Do you have any existing plans or documents', 'type' => 'radio',
					'options' => array( 'Yes — architectural / construction drawings', 'Yes — preliminary plans', 'Some documentation available', 'No plans or documents at this stage' ) ),
				array( 'name' => 'plans', 'label' => '+ Upload documents here', 'type' => 'file',
					'accept' => '.pdf,.jpg,.jpeg,.png,.heic,.dwg,.dxf,.doc,.docx,.zip',
					'hint' => 'PDF, JPG, PNG, DWG, DOC or ZIP' ),
			),
		),
		array(
			'panel' => 'fields', 'num' => '02.', 'title' => 'Project Plan',
			'heading' => 'Project Team',
			'section' => '02. Project Status',
			'fields' => array(
				array( 'name' => 'project_team', 'label' => 'Do you have a team you are working with in place', 'type' => 'checkbox',
					'options' => array( 'Architect', 'Draftsperson', 'Builder', 'Engineer', 'Certifier / planning consultants', 'Other', 'No project team has been appointed' ) ),
			),
		),

		/* ---------------------------------------------------------- 03 */
		array(
			'panel' => 'intro', 'num' => '03.', 'title' => 'The Scope',
			'heading' => 'Project Scope',
			'lede' => array( 'The scale and level of detail can vary greatly from one project to the next. Here, we’d like to understand which spaces are involved, the extent of the work, and the level of design support you’re looking for.' ),
		),
		array(
			'panel' => 'fields', 'num' => '03.', 'title' => 'The Scope',
			'heading' => 'Project Size',
			'section' => '03. The Scope',
			'fields' => array(
				array( 'name' => 'project_size', 'label' => 'What is the approximate size of the project', 'type' => 'unit', 'unit' => 'm²' ),
				array( 'name' => 'size_unsure', 'label' => '', 'type' => 'checkbox', 'options' => array( 'Unsure' ) ),
			),
		),
		array(
			'panel' => 'fields', 'num' => '03.', 'title' => 'The Scope',
			'heading' => 'Project Scope',
			'section' => '03. The Scope',
			'fields' => array(
				array( 'name' => 'areas', 'label' => 'Can you describe the areas included in the project?', 'type' => 'textarea',
					'note' => 'Let us know which spaces are involved and, where relevant, how many of each. For example: kitchen, 3 bathrooms, 4 bedrooms, living and dining areas, reception, retail space, bar, treatment rooms, staff areas or outdoor spaces.' ),
			),
		),
		array(
			'panel' => 'fields', 'num' => '03.', 'title' => 'The Scope',
			'heading' => 'Our Involvement',
			'section' => '03. The Scope',
			'fields' => array(
				array( 'name' => 'involvement', 'label' => 'What level of design support are you looking for?', 'type' => 'radio',
					'options' => array(
						'Full-service interior design' => 'We’d like More Atelier to guide from initial concept through to completion.',
						'Interior design for selected areas' => 'We need design assistance with particular rooms, spaces or areas of the project.',
						'Furniture, furnishings & styling' => 'The space is largely resolved and we need help with furniture, lighting, art and the finishing layers.',
						'Design consultation & direction' => 'We’re looking for professional guidance and ideas rather than a complete design service.',
						'We’re not sure yet' => 'We’d like More Atelier to review the project and recommend the right level of involvement.',
					) ),
			),
		),

		/* ---------------------------------------------------------- 04 */
		array(
			'panel' => 'intro', 'num' => '04.', 'title' => 'The Vision',
			'heading' => 'The Vision',
			'lede' => array( 'Beyond the practical requirements, we want to understand what you’re drawn to and how you want the space to feel. Share the references, ideas and details that are shaping your vision — even if they’re still evolving.' ),
		),
		array(
			'panel' => 'fields', 'num' => '04.', 'title' => 'The Vision',
			'heading' => 'The Vision',
			'section' => '04. The Vision',
			'fields' => array(
				array( 'name' => 'feel', 'label' => 'How would you like the finished space to feel', 'type' => 'textarea',
					'note' => 'Tell us about the atmosphere, experience or overall feeling you’re hoping to create.' ),
				array( 'name' => 'drawn_to', 'label' => 'What are you drawn to?', 'type' => 'textarea',
					'note' => 'Share any interiors, places, materials or design references that resonate with you. Equally, let us know if there’s anything you definitely don’t want.' ),
			),
		),
		array(
			'panel' => 'fields', 'num' => '04.', 'title' => 'The Vision',
			'heading' => 'Existing Space',
			'section' => '04. The Vision',
			'fields' => array(
				array( 'name' => 'existing', 'label' => 'Is there anything existing that needs to be retained or incorporated into the design?', 'type' => 'textarea',
					'note' => 'For example, furniture, artwork, architectural features, existing finishes or brand elements.' ),
			),
		),
		array(
			'panel' => 'fields', 'num' => '04.', 'title' => 'The Vision',
			'heading' => 'Inspiration',
			'section' => '04. The Vision',
			'fields' => array(
				array( 'name' => 'inspiration_links', 'label' => 'Do you have any inspiration you’d like to share with us?', 'type' => 'textarea',
					'note' => 'Upload images or paste links to Pinterest boards, Instagram saves or other references.',
					'placeholder' => 'Paste any links here' ),
				array( 'name' => 'inspiration', 'label' => '+ Upload documents', 'type' => 'file',
					'accept' => '.pdf,.jpg,.jpeg,.png,.heic,.webp,.gif,.zip',
					'hint' => 'JPG, PNG, PDF or ZIP' ),
			),
		),

		/* ---------------------------------------------------------- 05 */
		array(
			'panel' => 'intro', 'num' => '05.', 'title' => 'The Investment',
			'heading' => 'The Investment',
			'lede' => array( 'A clear understanding of investment allows us to approach the project realistically from the beginning and recommend a scope that feels appropriate. These figures don’t need to be exact; an indication simply helps us understand the parameters we’ll be designing within.' ),
		),
		array(
			'panel' => 'fields', 'num' => '05.', 'title' => 'The Investment',
			'heading' => 'Overall Project Budget',
			'section' => '05. The Investment',
			'fields' => array(
				array( 'name' => 'project_budget', 'label' => 'What is the anticipated overall project budget?', 'type' => 'radio',
					'note' => 'This refers to the construction or fit-out, joinery, finishes, fixtures and furnishings, and excludes More Atelier’s design fees.',
					'options' => array( 'Under $100,000', '$100,000 – $250,000', '$250,000 – $500,000', '$500,000 – $1M', '$1M – $2M', '$2M+', 'Yet to be determined' ) ),
			),
		),
		array(
			'panel' => 'fields', 'num' => '05.', 'title' => 'The Investment',
			'heading' => 'Design Investment',
			'section' => '05. The Investment',
			'fields' => array(
				array( 'name' => 'design_budget', 'label' => 'Have you considered an investment for interior design services?', 'type' => 'radio',
					'note' => 'An indication helps us understand the level of service you are anticipating.',
					'options' => array( 'Under $10,000', '$10,000 – $20,000', '$20,000 – $40,000', '$40,000 – $60,000', '$60,000 – $100,000', '$100,000 +', 'We haven’t set a design budget yet', 'We’d like More Atelier to advise us' ),
					'sentence_case' => array( 'We haven’t set a design budget yet', 'We’d like More Atelier to advise us' ) ),
			),
		),
		array(
			'panel' => 'fields', 'num' => '05.', 'title' => 'The Investment',
			'heading' => 'Project Costings',
			'section' => '05. The Investment',
			'submit' => true,
			'fields' => array(
				array( 'name' => 'costed', 'label' => 'Has the project been professionally costed at this stage?', 'type' => 'radio',
					'options' => array( 'Yes', 'Partially', 'Not yet' ) ),
			),
		),

		array(
			'panel' => 'thanks',
			'heading' => 'Thank You',
			'lede' => array(
				'Thank you for taking the time to share your project with More Atelier.',
				'We are looking forward to spending time with what you have shared, understanding what matters most to you, and considering how More Atelier might bring it all together.',
				'From here, we will review your brief and return with an initial sense of scope, design investment and the right next step for your project.',
				'We look forward to continuing the conversation.',
			),
		),
	);
}

/** Flat list of every answerable field, in brief order. */
function madb_fields() {
	$out = array();
	foreach ( madb_schema() as $panel ) {
		if ( empty( $panel['fields'] ) ) { continue; }
		foreach ( $panel['fields'] as $f ) {
			$f['section'] = isset( $panel['section'] ) ? $panel['section'] : '';
			$f['group']   = isset( $panel['heading'] ) ? $panel['heading'] : '';
			$out[ $f['name'] ] = $f;
		}
	}
	return $out;
}
