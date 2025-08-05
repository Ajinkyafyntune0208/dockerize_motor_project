import React from "react";
import CancelIcon from "@material-ui/icons/Cancel";
import _ from "lodash";
import { currencyFormater } from "utils";
import PropTypes from "prop-types";
import {
  CardDiv,
  CompareButton,
  NoPlansDiv,
  StyledDiv1,
  TopPop,
} from "../ComparePageStyle";

const Content = ({
  lessThan768,
  compareQuotesList,
  validQuote,
  shortTermType,
  tempData,
  removeFn,
  compareFn,
  closePopup,
}) => {
  console.log(compareQuotesList, "compareQuotesList in Context jsx");
  return (
    <TopPop>
      <div
        style={{ overflow: lessThan768 ? "auto" : "hidden", overflowX: "clip" }}
      >
        <h4
          className="mt-4 add_plans"
          style={{
            paddingLeft: lessThan768 ? "" : "40px",
            paddingBottom: "20px",
            paddingTop: lessThan768 ? "20px" : "",
            fontSize: lessThan768 ? "20px" : "",
            textAlign: lessThan768 ? "center" : "",
          }}
        >
          Add upto 3 plans to compare
        </h4>
        <div
          className="row mt-4"
          style={{
            borderBottom: "1px dotted black",
            paddingBottom: "20px",
            width: "100%",
            padding: "0px 20px 20px 20px",
          }}
        >
          {compareQuotesList?.map((item) =>
            item.idv ? (
              <div className="col-6 col-md-4">
                <CardDiv
                  style={{
                    borderRadius: "5px",
                    width: "100%",
                    height: "100%",
                    padding: "10px 0px",
                    display: "flex",
                    flexDirection: "column",
                    justifyContent: "center",
                    alignItems: "center",
                    margin: "0px 20px",
                    boxShadow: "rgb(0 131 10 / 18%) 0px 5px 20px 0px",
                  }}
                >
                  {validQuote.length > 1 && (
                    <CancelIcon
                      onClick={() => removeFn(item)}
                      // onClick={() => {
                      //   compareFn2(item);
                      // }}
                      style={{
                        position: "absolute",
                        top: lessThan768 ? "-10px" : "-12px",
                        right: lessThan768 ? "-15px" : "-17px",
                        fontSize: lessThan768 ? "18px" : "24px",
                        background: "#fff",
                        borderRadius: "50%",
                        cursor: "pointer",
                        color: "black",
                      }}
                    />
                  )}
                  <img
                    src={item?.companyLogo}
                    alt="myImage"
                    style={{
                      width: lessThan768 ? "90px" : "100%",
                      height: lessThan768 ? "" : "80px",
                      padding: lessThan768 ? "" : "0px 40px",
                      objectFit: "cover",
                    }}
                  />
                  {/* <p
                                                style={{
                                                    fontSize: "13px",
                                                    margin: "10px",
                                                    textAlign: "center",
                                                }}
                                            >
                                                {item?.companyName}
                                            </p> */}

                  <div
                    style={{
                      display: "flex",
                      justifyContent: "space-around",
                      width: "100%",
                      fontSize: "13.5px",
                      marginTop: "10px",
                      flexDirection: lessThan768 ? "column" : "",
                      alignItems: lessThan768 ? "" : "center",
                      paddingLeft: lessThan768 ? "15px" : "",
                    }}
                  >
                    <p style={{ margin: lessThan768 ? "0px" : "" }}>
                      <div
                        style={{
                          textAlign: "left",
                        }}
                      >
                        Base premium:
                      </div>
                      <br />
                      <strong
                        style={{
                          fontSize: "14px",
                          position: "relative",
                          bottom: "15px",
                        }}
                      >
                        <span
                          style={{
                            fontSize: "14px",
                          }}
                        >
                          ₹
                        </span>{" "}
                        {currencyFormater(item?.finalPayableAmount)}
                      </strong>
                    </p>

                    <p style={{ margin: lessThan768 ? "0px" : "" }}>
                      <div style={{ textAlign: "left" }}>IDV: </div>
                      <br />
                      <strong
                        style={{
                          fontSize: "14px",
                          position: "relative",
                          bottom: "15px",
                        }}
                      >
                        <span
                          style={{
                            fontSize: "14px",
                          }}
                        >
                          ₹
                        </span>{" "}
                        {currencyFormater(item?.idv)}
                      </strong>
                    </p>
                  </div>
                </CardDiv>
              </div>
            ) : (
              <div className={lessThan768 ? "col-6" : "col-4"}>
                <NoPlansDiv
                  style={{
                    width: "100%",
                    height: "100%",
                    display: "flex",
                    flexDirection: "column",
                    justifyContent: "center",
                    alignItems: "center",
                    margin: "0px 20px",
                  }}
                >
                  <i
                    className="fa fa-plus"
                    style={{
                      marginBottom: "5px",
                      boxShadow: "1px 1px 5px grey",
                      fontSize: "25px",
                      background: "#fff",
                      width: "35px",
                      height: "35px",
                      borderRadius: "50%",
                      display: "flex",
                      justifyContent: "center",
                      alignItems: "center",
                      color: "grey",
                    }}
                  ></i>
                  <p className="text-center">No Plans Added</p>
                </NoPlansDiv>
              </div>
            )
          )}
        </div>
        {/* <p>All available quotes</p> */}
        <div
          className="row"
          //	className="newProductList"
          style={{
            padding: "10px 32px",
            maxHeight: "214px",
            overflow: "scroll",
            overflowX: "clip",
            maxWidth: "915px",
          }}
        >
          {(shortTermType
            ? shortTermType
            : tempData.quoteComprehesiveGrouped
          )?.map((singleQuote, index) => (
            <div className="col-md-4" style={{ width: "100%", height: "100%" }}>
              <div
                className="temp_data_quotes"
                style={{
                  boxShadow: "1px 1px 5px grey",
                  padding: "10px",
                  margin: "10px",
                  borderRadius: "6px",
                  cursor: "pointer",
                }}
                onClick={() => {
                  if (
                    _.compact(
                      compareQuotesList?.map((x) => x.policyId)
                    )?.includes(singleQuote?.policyId)
                  ) {
                    removeFn(singleQuote);
                  } else {
                    compareFn(singleQuote);
                  }
                }}
              >
                <div
                  style={{
                    display: "flex",
                    justifyContent: "space-between",
                    padding: "5px",
                    flexDirection: "column",
                  }}
                >
                  <img
                    src={singleQuote.companyLogo}
                    width="150"
                    height="75"
                    style={{ margin: "auto", objectFit: "contain" }}
                    alt="company logo"
                  />
                  <div
                    style={{
                      display: "flex",
                      justifyContent: "space-around",
                      width: "100%",
                      fontSize: "13.5px",
                      marginTop: "10px",
                      // flexDirection: lessThan768 ? "column" : "",
                      alignItems: "center",
                    }}
                  >
                    <p style={{ marginBottom: "0px" }}>
                      <div
                        style={{
                          textAlign: "left",
                        }}
                      >
                        Base premium:
                      </div>
                      <br />
                      <strong
                        style={{
                          fontSize: "14px",
                          position: "relative",
                          bottom: "15px",
                        }}
                      >
                        <span
                          style={{
                            fontSize: "14px",
                          }}
                        >
                          ₹
                        </span>{" "}
                        {currencyFormater(singleQuote?.finalPayableAmount)}
                      </strong>
                    </p>

                    <p style={{ marginBottom: "0px" }}>
                      <div style={{ textAlign: "left" }}>IDV:</div>
                      <br />
                      <strong
                        style={{
                          fontSize: "14px",
                          position: "relative",
                          bottom: "15px",
                        }}
                      >
                        <span
                          style={{
                            fontSize: "14px",
                          }}
                        >
                          ₹
                        </span>{" "}
                        {currencyFormater(singleQuote?.idv)}
                      </strong>
                    </p>
                  </div>
                  <StyledDiv1>
                    <span
                      className="group-check float-right"
                      style={{ width: "5%" }}
                    >
                      {" "}
                      {_.compact(
                        compareQuotesList?.map((x) => x.policyId)
                      )?.includes(singleQuote?.policyId) ? (
                        <i
                          style={{
                            background: "green",
                            color: "#fff",
                            marginTop: "15px",
                            fontSize: "16px",
                          }}
                          className="fa fa-check"
                        ></i>
                      ) : (
                        <i
                          style={{
                            background: "#fff",
                            color: "#fff",
                            marginTop: "15px",
                            border: "0.5px solid #0000007a",
                            fontSize: "13.5px",
                          }}
                          className="fa fa-check"
                        ></i>
                      )}
                    </span>
                  </StyledDiv1>
                  {/* <i className="fa fa-plus" onClick={() => addItems(singleQuote)}>Add</i> */}
                </div>
                {/* <p style={{ fontSize: '13px' }}> {singleQuote.companyName} </p> */}
              </div>
            </div>
          ))}
        </div>
        {/* {ids.length === 3 && closePop()} */}
        <div style={{ margin: "20px 0px", textAlign: "center" }}>
          <CompareButton
            // onClick={closePop}
            onClick={closePopup}
            style={{
              border: "none",
              padding: "15px 50px",
              letterSpacing: "0.5px",
              color: "#fff",
              borderRadius: "5px",
            }}
          >
            Compare
          </CompareButton>
        </div>
      </div>
    </TopPop>
  );
};

export default Content;

// PropTypes
Content.propTypes = {
  lessThan768: PropTypes.bool,
  compareQuotesList: PropTypes.object,
  shortTermType: PropTypes.bool,
  validQuote: PropTypes.object,
  tempData: PropTypes.object,
  compareFn: PropTypes.func,
  removeFn: PropTypes.func,
  closePopup: PropTypes.bool,
};

// DefaultTypes
Content.defaultProps = {
  compareQuotesList: [],
  validQuote: [],
  tempData: {},
  compareFn: () => {},
  removeFn: () => {},
  closePopup: false,
};
