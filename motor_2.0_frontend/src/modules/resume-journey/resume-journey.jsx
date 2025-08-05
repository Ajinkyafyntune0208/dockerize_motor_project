import { Loader } from "components";
import BrokenLinkMessage from "components/broken-link-message/broker-link-message";
import { RedirectURL } from "modules/Home/home.slice";
import { useEffect } from "react";
import { useDispatch } from "react-redux";

const ResumeJourney = () => {
  const dispatch = useDispatch();
  const query = new URLSearchParams(location.search);
  const enquiry_id = query.get("enquiry_id");
  useEffect(() => {
    enquiry_id && dispatch(RedirectURL({ enquiry_id: enquiry_id }, history));
  }, []);

  return (
    <div className="h-100 w-100">
      {enquiry_id ? <Loader /> : <BrokenLinkMessage />}
    </div>
  );
};

export default ResumeJourney;
