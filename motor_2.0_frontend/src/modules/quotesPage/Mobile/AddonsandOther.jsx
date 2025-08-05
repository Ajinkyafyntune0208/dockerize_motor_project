import React from "react";
import Styled from "../quotesStyle";
import SwipeableDrawer from "@mui/material/SwipeableDrawer";
import CloseIcon from "@mui/icons-material/Close";
import { AddOnsCard } from "../addOnCard/addOnCard";
import { TypeReturn } from "modules/type";

const AddonsandOther = ({
  temp_data,
  addOnsAndOthers,
  lessthan360,
  toggleDrawer,
  addonDrawer,
  setAddonDrawer,
  tab,
  type,
  setShortTerm3,
  setShortTerm6,
  policyTypeCode,
  setRelevant,
  isRelevant,
  setRenewalFilter,
  renewalFilter,
  setSortBy,
  sortBy,
  longTerm2,
  longTerm3,
  setLongterm2,
  setLongterm3,
  setQuoteComprehesiveGrouped,
  setQuoteComprehesiveGrouped1,
  setUngroupedQuoteShortTerm3,
  setUngroupedQuoteShortTerm6,
  setGroupedQuoteShortTerm3,
  setGroupedQuoteShortTerm6,
  setQuoteShortTerm3,
  setQuoteShortTerm6,
  setQuoteTpGrouped1,
  gstToggle,
  setGstToggle,
}) => {
  return (
    <Styled.MobileAddonButtonsContainer>
      {!temp_data?.odOnly && temp_data?.ownerTypeId === 1 && (
        <Styled.MobileAddonButton
          checked={addOnsAndOthers?.selectedCpa?.includes(
            "Compulsory Personal Accident"
          )}
          onClick={() => {
            document.getElementById(`Compulsory Personal Accident`) &&
              document.getElementById(`Compulsory Personal Accident`).click();
          }}
        >
          CPA{" "}
          {addOnsAndOthers?.selectedCpa?.includes(
            "Compulsory Personal Accident"
          ) && <i className="fa fa-check" style={{ color: "green" }}></i>}
        </Styled.MobileAddonButton>
      )}
      {temp_data?.tab !== "tab2" && (
        <>
          <Styled.MobileAddonButton
            min={true}
            lessthan360={lessthan360}
            checked={addOnsAndOthers?.selectedAddons?.includes(
              "zeroDepreciation"
            )}
            onClick={() => {
              document.getElementById(`Zero Depreciation`) &&
                document.getElementById(`Zero Depreciation`).click();
            }}
          >
            Zero Dep{" "}
            {addOnsAndOthers?.selectedAddons?.includes("zeroDepreciation") && (
              <i className="fa fa-check" style={{ color: "green" }}></i>
            )}
          </Styled.MobileAddonButton>

          <Styled.MobileAddonButton
            checked={addOnsAndOthers?.selectedAddons?.includes(
              "roadSideAssistance"
            )}
            onClick={() => {
              document.getElementById(`Road Side Assistance`) &&
                document.getElementById(`Road Side Assistance`).click();
            }}
          >
            RSA{" "}
            {addOnsAndOthers?.selectedAddons?.includes(
              "roadSideAssistance"
            ) && <i className="fa fa-check" style={{ color: "green" }}></i>}
          </Styled.MobileAddonButton>
        </>
      )}
      <>
        {["left"].map((anchor) => (
          <React.Fragment key={anchor}>
            <Styled.MobileMoreAddonButton onClick={toggleDrawer(anchor, true)}>
              <i
                style={
                  import.meta.env.VITE_BROKER === "ABIBL"
                    ? { verticalAlign: "middle", marginTop: "-0.5px" }
                    : {}
                }
                className="fa fa-plus-circle"
              ></i>
            </Styled.MobileMoreAddonButton>
            <SwipeableDrawer
              anchor={anchor}
              open={addonDrawer[anchor]}
              onClose={
                (toggleDrawer(anchor, false),
                () => {
                  setAddonDrawer(false);
                })
              }
              onOpen={toggleDrawer(anchor, true)}
              ModalProps={{
                keepMounted: true,
              }}
            >
              <Styled.AddonDrawerContent>
                <Styled.AddonDrawerHeader>
                  <div className="addonMobileTitle">Addons</div>

                  <CloseIcon
                    fontSize={"1.25rem"}
                    onClick={() => {
                      setAddonDrawer(false);
                    }}
                  />
                </Styled.AddonDrawerHeader>
                <AddOnsCard
                  tab={tab}
                  type={TypeReturn(type)}
                  setShortTerm3={setShortTerm3}
                  setShortTerm6={setShortTerm6}
                  policyTypeCode={policyTypeCode}
                  setRelevant={setRelevant}
                  isRelevant={isRelevant}
                  setRenewalFilter={setRenewalFilter}
                  renewalFilter={renewalFilter}
                  setSortBy={setSortBy}
                  sortBy={sortBy}
                  longTerm2={longTerm2}
                  longTerm3={longTerm3}
                  setLongterm2={setLongterm2}
                  setLongterm3={setLongterm3}
                  setQuoteComprehesiveGrouped={setQuoteComprehesiveGrouped}
                  setQuoteComprehesiveGrouped1={setQuoteComprehesiveGrouped1}
                  setUngroupedQuoteShortTerm3={setUngroupedQuoteShortTerm3}
                  setUngroupedQuoteShortTerm6={setUngroupedQuoteShortTerm6}
                  setGroupedQuoteShortTerm3={setGroupedQuoteShortTerm3}
                  setGroupedQuoteShortTerm6={setGroupedQuoteShortTerm6}
                  setQuoteShortTerm3={setQuoteShortTerm3}
                  setQuoteShortTerm6={setQuoteShortTerm6}
                  setQuoteTpGrouped1={setQuoteTpGrouped1}
                  gstToggle={gstToggle}
                  setGstToggle={setGstToggle}
                />
                <Styled.AddonDrawerFooter>
                  <div
                    className="addonDrawerFooterClear"
                    onClick={() => {
                      document.getElementById(`clearAllAddons`) &&
                        document.getElementById(`clearAllAddons`).click();
                    }}
                  >
                    <u>Clear All</u>
                  </div>
                  <div
                    className="addonDrawerFooterApply"
                    onClick={() => {
                      document.getElementById(`updateDiscountsButton`) &&
                        document
                          .getElementById(`updateDiscountsButton`)
                          .click();

                      document.getElementById(`updateAccesoriesButton`) &&
                        document
                          .getElementById(`updateAccesoriesButton`)
                          .click();

                      document.getElementById(`updateAdditionsButton`) &&
                        document
                          .getElementById(`updateAdditionsButton`)
                          .click();
                      setAddonDrawer(false);
                    }}
                  >
                    Apply
                  </div>
                </Styled.AddonDrawerFooter>
              </Styled.AddonDrawerContent>
            </SwipeableDrawer>
          </React.Fragment>
        ))}
      </>
    </Styled.MobileAddonButtonsContainer>
  );
};

export default AddonsandOther;
