<?php

namespace DTApi\Http\Controllers;

use DTApi\Models\Job;
use DTApi\Http\Requests;
use DTApi\Models\Distance;
use Illuminate\Http\Request;
use DTApi\Repository\BookingRepository;

/**
 * Class BookingController
 * @package DTApi\Http\Controllers
 */

class BookingController extends Controller
{
	protected $repository;
	
	public function __construct(BookingRepository $bookingRepository)
	{
		$this->repository = $bookingRepository;
	}
	
	public function index(Request $request)
	{
		if ($request->has('user_id')) {
			$response = $this->repository->getUsersJobs($request->get('user_id'));
		} elseif (in_array($request->__authenticatedUser->user_type, [env('ADMIN_ROLE_ID'), env('SUPERADMIN_ROLE_ID')])) {
			$response = $this->repository->getAll($request);
		}
		
		return response($response ?? null);
	}
	
	public function show($id)
	{
		$job = $this->repository->with('translatorJobRel.user')->findOrFail($id);
		return response($job);
	}
	
	public function store(Request $request)
	{
		$response = $this->repository->store($request->__authenticatedUser, $request->all());
		return response($response);
	}
	
	public function update($id, Request $request)
	{
		$response = $this->repository->updateJob($id, $request->except(['_token', 'submit']), $request->__authenticatedUser);
		return response($response);
	}
	
	public function immediateJobEmail(Request $request)
	{
		$adminSenderEmail = config('app.adminemail');
		$data = $request->all();
		
		$response = $this->repository->storeJobEmail($data, $adminSenderEmail);
		
		return response($response);
	}
	
	public function getHistory(Request $request)
	{
		if ($request->has('user_id')) {
			$response = $this->repository->getUsersJobsHistory($request->get('user_id'), $request);
			return response($response);
		}
		
		return response(null);
	}
	
	public function acceptJob(Request $request)
	{
		$response = $this->repository->acceptJob($request->all(), $request->__authenticatedUser);
		return response($response);
	}
	
	public function acceptJobWithId(Request $request)
	{
		$response = $this->repository->acceptJobWithId($request->get('job_id'), $request->__authenticatedUser);
		return response($response);
	}
	
	public function cancelJob(Request $request)
	{
		$response = $this->repository->cancelJobAjax($request->all(), $request->__authenticatedUser);
		return response($response);
	}
	
	public function endJob(Request $request)
	{
		$data = $request->all();
		$response = $this->repository->endJob($data);
		return response($response);
	}
	
	public function customerNotCall(Request $request)
	{
		$data = $request->all();
		$response = $this->repository->customerNotCall($data);
		return response($response);
	}
	public function getPotentialJobs(Request $request)
	{
		$data = $request->all();
		$user = $request->__authenticatedUser;
		
		$response = $this->repository->getPotentialJobs($user);
		
		return response($response);
	}
	
	public function distanceFeed(Request $request)
	{
		$data = $request->all();
		
		// Extracting values with default empty values
		$distance = $data['distance'] ?? '';
		$time = $data['time'] ?? '';
		$jobid = $data['jobid'] ?? '';
		$session = $data['session_time'] ?? '';
		$flagged = ($data['flagged'] == 'true' && $data['admincomment'] !== '') ? 'yes' : 'no';
		$manually_handled = ($data['manually_handled'] == 'true') ? 'yes' : 'no';
		$by_admin = ($data['by_admin'] == 'true') ? 'yes' : 'no';
		$admincomment = $data['admincomment'] ?? '';
		
		// Update distance and time if either is present
		if ($time || $distance) {
			Distance::where('job_id', '=', $jobid)->update(['distance' => $distance, 'time' => $time]);
		}
		
		// Update job details if any of the specified fields are present
		if ($admincomment || $session || $flagged || $manually_handled || $by_admin) {
			Job::where('id', '=', $jobid)->update([
				'admin_comments' => $admincomment,
				'flagged' => $flagged,
				'session_time' => $session,
				'manually_handled' => $manually_handled,
				'by_admin' => $by_admin
			]);
		}
		
		return response('Record updated!');
	}
	
	public function reopen(Request $request)
	{
		$data = $request->all();
		$response = $this->repository->reopen($data);
		
		return response($response);
	}
	
	public function resendNotifications(Request $request)
	{
		$data = $request->all();
		$job = $this->repository->find($data['jobid']);
		$job_data = $this->repository->jobToData($job);
		$this->repository->sendNotificationTranslator($job, $job_data, '*');
		
		return response(['success' => 'Push sent']);
	}
	
	/**
	 * Sends SMS to Translator
	 * @param Request $request
	 * @return \Illuminate\Contracts\Routing\ResponseFactory|\Symfony\Component\HttpFoundation\Response
	 */
	public function resendSMSNotifications(Request $request)
	{
		$data = $request->all();
		$job = $this->repository->find($data['jobid']);
		$job_data = $this->repository->jobToData($job);
		
		try {
			$this->repository->sendSMSNotificationToTranslator($job);
			return response(['success' => 'SMS sent']);
		} catch (\Exception $e) {
			return response(['success' => $e->getMessage()]);
		}
	}
}

