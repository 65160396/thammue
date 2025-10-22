/* /js/ex.exchange.js */
(function(){
  const API_BASE = '/page/backend';
  async function exFetch(path, opts={}){
    const res = await fetch(API_BASE + path, {
      headers:{'Content-Type':'application/json'},
      credentials:'include',
      ...opts
    });
    if (!res.ok) throw new Error('HTTP '+res.status);
    return await res.json();
  }
  async function exCreateRequest({requested_item_id, offered_item_id, message}){
    return exFetch('/ex_create_request.php', { method:'POST', body: JSON.stringify({requested_item_id, offered_item_id, message}) });
  }
  async function exAccept(request_id){ return exFetch('/ex_accept_request.php', { method:'POST', body: JSON.stringify({request_id}) }); }
  async function exDecline(request_id){ return exFetch('/ex_decline_request.php', { method:'POST', body: JSON.stringify({request_id}) }); }
  async function exCancel(request_id){ return exFetch('/ex_cancel_request.php', { method:'POST', body: JSON.stringify({request_id}) }); }
  async function exListMyRequests(){ return exFetch('/ex_list_my_requests.php'); }
  async function exUpdateMeeting({request_id, scheduled_at, place, note}){
    return exFetch('/ex_meeting_update.php', { method:'POST', body: JSON.stringify({request_id, scheduled_at, place, note}) });
  }
  async function exListNoti(){ return exFetch('/ex_list_notifications.php'); }
  window.Ex = { exCreateRequest, exAccept, exDecline, exCancel, exListMyRequests, exUpdateMeeting, exListNoti };
})();
