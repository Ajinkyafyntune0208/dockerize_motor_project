import { reloadPage } from "utils";
const BrokenLinkMessage = () => {
  const baseName =
    import.meta.env.VITE_BASENAME === "NA" ? "" : import.meta.env.VITE_BASENAME;
  const formattedURL = `${window.location.origin}${
    baseName ? `/${baseName}` : ""
  }/landing-page`;
  return (
    <div className="h-100 w-100 d-flex flex-column justify-content-center align-items-center">
      <div className="container-journey">
        <div className="errorContainer">
          <span className="errorHeader">Oops !</span>
          <p className="errorText">The link is no longer active</p>
          <button
            className="errorButton"
            type="button"
            onClick={() => reloadPage(formattedURL)}
          >
            Go to Homepage
          </button>
        </div>
        <div className="footstepImage">
          <img src="./assets/images/footsteps.png" alt="broken-link" />
        </div>
      </div>
    </div>
  );
};

export default BrokenLinkMessage;
