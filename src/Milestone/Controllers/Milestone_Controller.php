<?php

namespace WeDevs\PM\Milestone\Controllers;

use WP_REST_Request;
use WeDevs\PM\Milestone\Models\Milestone;
use League\Fractal;
use League\Fractal\Resource\Item as Item;
use League\Fractal\Resource\Collection as Collection;
use League\Fractal\Pagination\IlluminatePaginatorAdapter;
use WeDevs\PM\Common\Traits\Transformer_Manager;
use WeDevs\PM\Milestone\Transformers\Milestone_Transformer;
use WeDevs\PM\Common\Traits\Request_Filter;
use WeDevs\PM\Common\Models\Meta;
use Carbon\Carbon;
use Illuminate\Database\Capsule\Manager as DB;

class Milestone_Controller {

    use Transformer_Manager, Request_Filter;

    public function index( WP_REST_Request $request ) {
        $project_id = $request->get_param( 'project_id' );
        $per_page = $request->get_param( 'per_page' );
        $per_page = $per_page ? $per_page : 15;

        $page = $request->get_param( 'page' );
        $page = $page ? $page : 1;

        $metas = Meta::with( 'milestone.achieve_date_field', 'milestone.status_field' )
            ->where( 'entity_type', 'milestone' )
            ->where( 'meta_key', 'status' )
            ->where( 'project_id', $project_id )
            ->orderBy( 'meta_value', 'ASC' )
            ->paginate( $per_page, ['*'], 'page', $page );

        $meta_collection = $metas->getCollection();
        
        $milestone_collection = $this->get_milestone_collection( $meta_collection );

        $resource = new Collection( $milestone_collection, new Milestone_Transformer );
        $resource->setPaginator( new IlluminatePaginatorAdapter( $metas ) );


        return $this->get_response( $resource );
    }

    private function get_milestone_collection( $metas = [] ) {
        $milestones = [];

        foreach ($metas as $meta) {
            $milestones[] = $meta->milestone;
        }

        return $milestones;
    }

    public function show( WP_REST_Request $request ) {
        $project_id   = $request->get_param( 'project_id' );
        $milestone_id = $request->get_param( 'milestone_id' );

        $milestone = Milestone::where( 'id', $milestone_id )
            ->where( 'project_id', $project_id )
            ->first();

        $resource = new Item( $milestone, new Milestone_Transformer );

        return $this->get_response( $resource );
    }

    public function store( WP_REST_Request $request ) {
        // Grab non empty user input
        $data = $this->extract_non_empty_values( $request );

        // Milestone achieve date
        $achieve_date = $request->get_param( 'achieve_date' );

        // Create a milestone
        $milestone    = Milestone::create( $data );

        // Set 'achieve_date' as milestone meta data
        Meta::create([
            'entity_id'   => $milestone->id,
            'entity_type' => 'milestone',
            'meta_key'    => 'achieve_date',
            'meta_value'  => $achieve_date ? make_carbon_date( $achieve_date ) : null,
            'project_id'  => $milestone->project_id,
        ]);

        Meta::create([
            'entity_id'   => $milestone->id,
            'entity_type' => 'milestone',
            'meta_key'    => 'status',
            'meta_value'  => Milestone::INCOMPLETE,
            'project_id'  => $milestone->project_id,
        ]);

        // Transform milestone data
        $resource  = new Item( $milestone, new Milestone_Transformer );

        $message = [
            'message' => pm_get_text('success_messages.milestone_created')
        ];
        $response = $this->get_response( $resource, $message );
        do_action("pm_after_new_milestone", $response, $request->get_params() );

        return $response;
    }

    public function update( WP_REST_Request $request ) {
        // Grab non empty user data
        $data         = $this->extract_non_empty_values( $request );
        $achieve_date = $request->get_param( 'achieve_date' );
        $status       = $request->get_param( 'status' );

        // Set project id from url parameter
        $project_id   = $request->get_param( 'project_id' );

        // Set milestone id from url parameter
        $milestone_id = $request->get_param( 'milestone_id' );

        // Find milestone associated with project id and milestone id
        $milestone    = Milestone::where( 'id', $milestone_id )
            ->where( 'project_id', $project_id )
            ->first();

        if ( $milestone ) {
            $milestone->update_model( $data );
        }

        if ( $milestone && $achieve_date ) {
            $meta = Meta::firstOrCreate([
                'entity_id'   => $milestone->id,
                'entity_type' => 'milestone',
                'meta_key'    => 'achieve_date',
                'project_id'  => $milestone->project_id,
            ]);

            $meta->meta_value = make_carbon_date( $achieve_date );
            $meta->save();
        }

        if ( $milestone && in_array( $status, Milestone::$status ) ) {
            $status = array_search( $status, Milestone::$status );
            $meta   = Meta::firstOrCreate([
                'entity_id'   => $milestone->id,
                'entity_type' => 'milestone',
                'meta_key'    => 'status',
                'project_id'  => $milestone->project_id,
            ]);

            $meta->meta_value = $status;
            $meta->save();
        }

        $resource = new Item( $milestone, new Milestone_Transformer );

        $message = [
            'message' => pm_get_text('success_messages.milestone_updated')
        ];

        $response = $this->get_response( $resource, $message );
        do_action("pm_after_update_milestone", $response, $request->get_params() );

        return $response;
    }

    public function destroy( WP_REST_Request $request ) {
        $project_id   = $request->get_param( 'project_id' );
        $milestone_id = $request->get_param( 'milestone_id' );

        $milestone = Milestone::where( 'id', $milestone_id )
            ->where( 'project_id', $project_id )
            ->first();

        $milestone->boardables()->delete();
        $milestone->metas()->delete();
        $milestone->delete();

        $message = [
            'message' => pm_get_text('success_messages.milestone_deleted')
        ];

        return $this->get_response(false, $message);
    }
}