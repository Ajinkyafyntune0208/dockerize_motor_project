import React from "react";
import { ErrorContainer1 } from "../quotesStyle";
import { CustomTooltip } from "components";
import _ from "lodash";

export const showingErrors = (
  errorIcBased,
  filterErrorComp,
  finalErrorTp,
  tab,
  versionId,
  lessthan767,
  temp_data,
  getIcLogoUrl,
  getErrorMsgComp,
  getErrorMsgTp,
  token
) => {
  //processing error labels
  const processLabels = (label) => {
    return label === "edelweiss"
      ? "Zuno"
      : label === "liberty_videocon"
      ? "Liberty GIC"
      : label === "cholla_mandalam"
      ? "chola mandalam"
      : label?.replace(/_/g, " ");
  };
  const processError = (error) => {
    let errorString =
      tab === "tab1" ? getErrorMsgComp(error) : getErrorMsgTp(error);
    return errorString?.replace(/LIBERTY_VIDEOCON/g, "Liberty GIC");
  };

  return (
    errorIcBased.length > 0 &&
    ((tab === "tab1" && filterErrorComp?.length > 0) ||
      (tab === "tab2" && finalErrorTp?.length > 0)) && (
      <div className="col-lg-12 ml-auto mt-4">
        <ErrorContainer1>
          <div
            className="is__getquote__title"
            style={lessthan767 ? { textAlign: "center" } : {}}
          >
            Insurance providers which didn't produce quotes for selected version
            {versionId}.
          </div>
          <div className="is__getquote__logos">
            {(tab === "tab1" ? filterErrorComp : finalErrorTp).map((item, i) => (
              <div>
                <CustomTooltip
                  rider="true"
                  id={"cpa__Tooltipvol" + i}
                  place={"bottom"}
                  customClassName="mt-3"
                  allowClick={lessthan767}
                  noDisplay={
                    import.meta.env.VITE_PROD === "YES" &&
                    _.isEmpty(
                      temp_data?.agentDetails?.filter(
                        (item) =>
                          item?.sellerType === "E" || item?.sellerType === "P"
                      )
                    )
                  }
                >
                  <img
                    alt="sks"
                    src={getIcLogoUrl(item)}
                    className="img-responsive form-check-label"
                    height="80"
                    width="auto"
                    data-tip={`<h3>${processLabels(
                      item
                    )}</h3> <div>${processError(item)}</div>`}
                    data-html={true}
                    data-for={"cpa__Tooltipvol" + i}
                  />
                </CustomTooltip>
              </div>
            ))}
          </div>
          <div className="is__getquote__info_label">
            Following are possible reasons
          </div>
          {token || ["KAROINSURE", "INSTANTBEEMA","VCARE","WOMINGO","HEROCARE","ONECLICK"].includes(import.meta.env.VITE_BROKER) ? (
            <ul className="error-list text-left p-0 mx-3">
              {(tab === "tab1" ? filterErrorComp : finalErrorTp).map((item) => (
                <li key={item} className="my-2">
                  <span className="error-name">
                    {processLabels(item)}
                    :-
                  </span>
                  {processError(item)}
                </li>
              ))}
            </ul>
          ) : (
            <ul className="is__getquote__info">
              <li>Selected vehicle is not mapped with the Insurer. </li>
              <li>
                Insurer plans are not available for selected combination.
              </li>
              <li>
                Insurer is not reachable to provide live quotes currently.
              </li>
            </ul>
          )}
        </ErrorContainer1>
      </div>
    )
  );
};
